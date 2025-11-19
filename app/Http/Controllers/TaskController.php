<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\AiJudge;
use App\Models\QuizJudge;
use App\Models\TextJudge;
use App\Models\AutoJudge;
use App\Models\QuizQuestionAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $tasks = Task::with('user')
            ->latest()
            ->paginate(10);

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $servers = \App\Models\Server::query()
            ->where('status', 'provisioned')
            ->orderBy('name')
            ->get(['id', 'name', 'ip_address']);

        return Inertia::render('tasks/create', [
            'servers' => $servers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        // Determine if sandbox is enabled
        $sandboxEnabled = $request->boolean('sandbox', false);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'docker_compose_yaml' => $sandboxEnabled ? 'required|string' : 'nullable|string',
            'score' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
            'server_id' => $sandboxEnabled ? 'required|exists:servers,id' : 'nullable|exists:servers,id',
            'pre_script' => 'nullable|string',
            'post_script' => 'nullable|string',
            'judge_type' => 'nullable|string|in:AiJudge,QuizJudge,TextJudge,AutoJudge',
            'sandbox' => 'nullable|boolean',
            'allowssh' => 'nullable|boolean',
            'timer' => 'nullable|integer|min:0',
            'warrning_timer' => 'nullable|integer|min:0',
            'warning_timer_sound' => 'nullable|boolean',
            'ai_judges' => 'nullable|array',
            'ai_judges.*.prompt' => 'required_with:ai_judges|string',
            'ai_judges.*.question' => 'required_with:ai_judges|string',
            'ai_judges.*.answer' => 'required_with:ai_judges|string',
            'quiz_questions' => 'nullable|array',
            'quiz_questions.*.question' => 'required_with:quiz_questions|string',
            'quiz_questions.*.options' => 'required_with:quiz_questions|array',
            'quiz_questions.*.options.*.text' => 'required|string',
            'quiz_questions.*.options.*.is_correct' => 'required|boolean',
            'text_judges' => 'nullable|array',
            'text_judges.*.question' => 'required_with:text_judges|string',
            'text_judges.*.answer' => 'required_with:text_judges|string',
            'judge_script' => 'nullable|string',
        ]);

        // Validate that judge configurations are provided when judge_type is set
        if (!empty($validated['judge_type'])) {
            if ($validated['judge_type'] === 'AiJudge' && empty($validated['ai_judges'])) {
                return back()->withErrors(['ai_judges' => 'At least one AI judge entry is required when AI Judge type is selected.'])->withInput();
            }
            if ($validated['judge_type'] === 'QuizJudge' && empty($validated['quiz_questions'])) {
                return back()->withErrors(['quiz_questions' => 'At least one quiz question is required when Quiz Judge type is selected.'])->withInput();
            }
            if ($validated['judge_type'] === 'TextJudge' && empty($validated['text_judges'])) {
                return back()->withErrors(['text_judges' => 'At least one text judge entry is required when Text Judge type is selected.'])->withInput();
            }
            if ($validated['judge_type'] === 'AutoJudge' && empty($validated['judge_script'])) {
                return back()->withErrors(['judge_script' => 'Judge script is required when Auto Judge type is selected.'])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $task = new Task($validated);
            $task->user_id = Auth::user()->id;
            $task->save();

            // Handle judge configurations based on judge_type
            if (!empty($validated['judge_type'])) {
                $this->saveJudgeConfiguration($task, $validated);
            }

            DB::commit();
            return redirect()->route('tasks.index')->with('success', 'Task created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create task: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task): Response
    {

        if (!$task) {
            abort(404, 'Task not found');
        }

        // Load judge configurations
        $judgeData = $this->loadJudgeConfigurations($task);

        return Inertia::render('tasks/show', [
            'task' => array_merge($task->toArray(), $judgeData),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task): Response
    {
        // Load judge configurations
        $judgeData = $this->loadJudgeConfigurations($task);

        // Get available servers for selection
        $servers = \App\Models\Server::query()
            ->where('status', 'provisioned')
            ->orderBy('name')
            ->get(['id', 'name', 'ip_address']);

        return Inertia::render('tasks/edit', [
            'task' => array_merge($task->toArray(), $judgeData),
            'servers' => $servers,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        // Determine if sandbox is enabled
        $sandboxEnabled = $request->boolean('sandbox', false);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'docker_compose_yaml' => $sandboxEnabled ? 'required|string' : 'nullable|string',
            'score' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
            'server_id' => $sandboxEnabled ? 'required|exists:servers,id' : 'nullable|exists:servers,id',
            'pre_script' => 'nullable|string',
            'post_script' => 'nullable|string',
            'judge_type' => 'nullable|string|in:AiJudge,QuizJudge,TextJudge,AutoJudge',
            'sandbox' => 'nullable|boolean',
            'allowssh' => 'nullable|boolean',
            'timer' => 'nullable|integer|min:0',
            'warrning_timer' => 'nullable|integer|min:0',
            'warning_timer_sound' => 'nullable|boolean',
            'ai_judges' => 'nullable|array',
            'ai_judges.*.prompt' => 'required_with:ai_judges|string',
            'ai_judges.*.question' => 'required_with:ai_judges|string',
            'ai_judges.*.answer' => 'required_with:ai_judges|string',
            'quiz_questions' => 'nullable|array',
            'quiz_questions.*.question' => 'required_with:quiz_questions|string',
            'quiz_questions.*.options' => 'required_with:quiz_questions|array',
            'quiz_questions.*.options.*.text' => 'required|string',
            'quiz_questions.*.options.*.is_correct' => 'required|boolean',
            'text_judges' => 'nullable|array',
            'text_judges.*.question' => 'required_with:text_judges|string',
            'text_judges.*.answer' => 'required_with:text_judges|string',
            'judge_script' => 'nullable|string',
        ]);

        // Validate that judge configurations are provided when judge_type is set
        if (!empty($validated['judge_type'])) {
            if ($validated['judge_type'] === 'AiJudge' && empty($validated['ai_judges'])) {
                return back()->withErrors(['ai_judges' => 'At least one AI judge entry is required when AI Judge type is selected.'])->withInput();
            }
            if ($validated['judge_type'] === 'QuizJudge' && empty($validated['quiz_questions'])) {
                return back()->withErrors(['quiz_questions' => 'At least one quiz question is required when Quiz Judge type is selected.'])->withInput();
            }
            if ($validated['judge_type'] === 'TextJudge' && empty($validated['text_judges'])) {
                return back()->withErrors(['text_judges' => 'At least one text judge entry is required when Text Judge type is selected.'])->withInput();
            }
            if ($validated['judge_type'] === 'AutoJudge' && empty($validated['judge_script'])) {
                return back()->withErrors(['judge_script' => 'Judge script is required when Auto Judge type is selected.'])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $task->update($validated);

            // Delete existing judge records
            $task->aiJudges()->delete();
            $task->quizJudges()->delete();
            $task->textJudges()->delete();
            $task->autoJudge()->delete();

            // Handle judge configurations based on judge_type
            if (!empty($validated['judge_type'])) {
                $this->saveJudgeConfiguration($task, $validated);
            }

            DB::commit();
            return redirect()->route('tasks.index')->with('success', 'Task updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update task: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'Task deleted successfully');
    }

    /**
     * Save judge configuration based on judge type
     */
    private function saveJudgeConfiguration(Task $task, array $validated): void
    {
        switch ($validated['judge_type']) {
            case 'AiJudge':
                if (!empty($validated['ai_judges'])) {
                    foreach ($validated['ai_judges'] as $aiJudge) {
                        AiJudge::create([
                            'task_id' => $task->id,
                            'prompt' => $aiJudge['prompt'],
                            'question' => $aiJudge['question'],
                            'answer' => $aiJudge['answer'],
                        ]);
                    }
                }
                break;

            case 'QuizJudge':
                if (!empty($validated['quiz_questions'])) {
                    foreach ($validated['quiz_questions'] as $quizQuestion) {
                        $quizJudge = QuizJudge::create([
                            'task_id' => $task->id,
                            'questions' => json_encode($quizQuestion['question']),
                        ]);

                        // Create quiz question answers
                        if (!empty($quizQuestion['options'])) {
                            foreach ($quizQuestion['options'] as $option) {
                                QuizQuestionAnswer::create([
                                    'quiz_judge_id' => $quizJudge->id,
                                    'choice' => $option['text'],
                                    'is_correct' => $option['is_correct'],
                                ]);
                            }
                        }
                    }
                }
                break;

            case 'TextJudge':
                if (!empty($validated['text_judges'])) {
                    foreach ($validated['text_judges'] as $textJudge) {
                        TextJudge::create([
                            'task_id' => $task->id,
                            'questions' => $textJudge['question'],
                            'answers' => $textJudge['answer'],
                        ]);
                    }
                }
                break;

            case 'AutoJudge':
                if (!empty($validated['judge_script'])) {
                    AutoJudge::create([
                        'task_id' => $task->id,
                        'judge_script' => $validated['judge_script'],
                    ]);
                }
                break;
        }
    }

    /**
     * Load judge configurations for a task
     */
    private function loadJudgeConfigurations(Task $task): array
    {
        // Load all judge relationships
        $task->load(['aiJudges', 'quizJudges.quizQuestionAnswers', 'textJudges', 'autoJudge']);

        // Prepare judge data array
        $judgeData = [
            'ai_judges' => [],
            'quiz_questions' => [],
            'text_judges' => [],
            'judge_script' => '',
        ];

        // Format data for frontend
        if ($task->judge_type === 'AiJudge') {
            $judgeData['ai_judges'] = $task->aiJudges->map(function ($aiJudge) {
                return [
                    'prompt' => $aiJudge->prompt,
                    'question' => $aiJudge->question,
                    'answer' => $aiJudge->answer,
                ];
            })->toArray();
        }

        if ($task->judge_type === 'QuizJudge') {
            $judgeData['quiz_questions'] = $task->quizJudges->map(function ($quizJudge) {
                return [
                    'question' => json_decode($quizJudge->questions, true),
                    'options' => $quizJudge->quizQuestionAnswers->map(function ($answer) {
                        return [
                            'text' => $answer->choice,
                            'is_correct' => $answer->is_correct,
                        ];
                    })->toArray(),
                ];
            })->toArray();
        }

        if ($task->judge_type === 'TextJudge') {
            $judgeData['text_judges'] = $task->textJudges->map(function ($textJudge) {
                return [
                    'question' => $textJudge->questions,
                    'answer' => $textJudge->answers,
                ];
            })->toArray();
        }

        if ($task->judge_type === 'AutoJudge' && $task->autoJudge) {
            $judgeData['judge_script'] = $task->autoJudge->judge_script;
        }

        return $judgeData;
    }
}
