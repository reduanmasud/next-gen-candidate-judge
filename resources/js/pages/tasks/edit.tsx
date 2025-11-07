import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { useForm, Head, Link, router } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { PlusIcon, Trash2Icon } from 'lucide-react';

type JudgeType = 'AiJudge' | 'QuizJudge' | 'TextJudge' | 'AutoJudge' | '';

interface AiJudgeEntry {
    prompt: string;
    question: string;
    answer: string;
}

interface QuizOption {
    text: string;
    is_correct: boolean;
}

interface QuizQuestion {
    question: string;
    options: QuizOption[];
}

interface TextJudgeEntry {
    question: string;
    answer: string;
}

interface Task {
    id: number;
    title: string;
    description: string;
    docker_compose_yaml: string;
    score: number;
    is_active: boolean;
    pre_script?: string;
    post_script?: string;
    judge_type?: JudgeType;
    ai_judges?: AiJudgeEntry[];
    quiz_questions?: QuizQuestion[];
    text_judges?: TextJudgeEntry[];
    judge_script?: string;
}

interface EditTaskProps {
    task: Task;
}

export default function EditTask({ task }: EditTaskProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tasks',
            href: '/tasks',
        },
        {
            title: task.title,
            href: `/tasks/${task.id}`,
        },
        {
            title: 'Edit',
            href: `/tasks/${task.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        title: task.title,
        description: task.description,
        docker_compose_yaml: task.docker_compose_yaml,
        score: task.score,
        is_active: task.is_active,
        pre_script: task.pre_script || '',
        post_script: task.post_script || '',
        judge_type: (task.judge_type || '') as JudgeType,
        ai_judges: task.ai_judges || [],
        quiz_questions: task.quiz_questions || [],
        text_judges: task.text_judges || [],
        judge_script: task.judge_script || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/tasks/${task.id}`);
    };

    const handleDelete = () => {
        router.delete(`/tasks/${task.id}`);
    };

    // AI Judge handlers
    const addAiJudge = () => {
        setData('ai_judges', [...data.ai_judges, { prompt: '', question: '', answer: '' }]);
    };

    const removeAiJudge = (index: number) => {
        setData('ai_judges', data.ai_judges.filter((_, i) => i !== index));
    };

    const updateAiJudge = (index: number, field: keyof AiJudgeEntry, value: string) => {
        const updated = [...data.ai_judges];
        updated[index][field] = value;
        setData('ai_judges', updated);
    };

    // Quiz Judge handlers
    const addQuizQuestion = () => {
        setData('quiz_questions', [...data.quiz_questions, { question: '', options: [{ text: '', is_correct: false }] }]);
    };

    const removeQuizQuestion = (index: number) => {
        setData('quiz_questions', data.quiz_questions.filter((_, i) => i !== index));
    };

    const updateQuizQuestion = (index: number, value: string) => {
        const updated = [...data.quiz_questions];
        updated[index].question = value;
        setData('quiz_questions', updated);
    };

    const addQuizOption = (questionIndex: number) => {
        const updated = [...data.quiz_questions];
        updated[questionIndex].options.push({ text: '', is_correct: false });
        setData('quiz_questions', updated);
    };

    const removeQuizOption = (questionIndex: number, optionIndex: number) => {
        const updated = [...data.quiz_questions];
        updated[questionIndex].options = updated[questionIndex].options.filter((_, i) => i !== optionIndex);
        setData('quiz_questions', updated);
    };

    const updateQuizOption = (questionIndex: number, optionIndex: number, field: keyof QuizOption, value: string | boolean) => {
        const updated = [...data.quiz_questions];
        updated[questionIndex].options[optionIndex][field] = value as never;
        setData('quiz_questions', updated);
    };

    // Text Judge handlers
    const addTextJudge = () => {
        setData('text_judges', [...data.text_judges, { question: '', answer: '' }]);
    };

    const removeTextJudge = (index: number) => {
        setData('text_judges', data.text_judges.filter((_, i) => i !== index));
    };

    const updateTextJudge = (index: number, field: keyof TextJudgeEntry, value: string) => {
        const updated = [...data.text_judges];
        updated[index][field] = value;
        setData('text_judges', updated);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${task.title}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card className="rounded-xl">
                    <CardHeader className="flex flex-col items-start gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle className="text-2xl">Edit Task</CardTitle>
                            <CardDescription>
                                Update the task details and configuration
                            </CardDescription>
                        </div>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive" disabled={processing}>
                                    Delete Task
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete this task?</DialogTitle>
                                <DialogDescription>
                                    This action cannot be undone. This will permanently
                                    delete the task and its associated data.
                                </DialogDescription>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">Cancel</Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        onClick={handleDelete}
                                        disabled={processing}
                                    >
                                        Confirm delete
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <form onSubmit={handleSubmit} className="grid gap-8 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <div className="space-y-2">
                                    <Label htmlFor="title">Task Title</Label>
                                    <Input
                                        id="title"
                                        type="text"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="Enter task title"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        A short, descriptive name for this task.
                                    </p>
                                    <InputError message={errors.title} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Enter task description"
                                        rows={4}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Provide details or instructions for the task.
                                    </p>
                                    <InputError message={errors.description} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="docker_compose_yaml">
                                        Docker Compose Configuration
                                    </Label>
                                    <Textarea
                                        id="docker_compose_yaml"
                                        value={data.docker_compose_yaml}
                                        onChange={(e) =>
                                            setData('docker_compose_yaml', e.target.value)
                                        }
                                        placeholder="Paste your docker-compose.yaml content here"
                                        rows={14}
                                        className="font-mono text-sm"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Paste valid YAML. Use services, volumes, and networks as needed.
                                    </p>
                                    <InputError message={errors.docker_compose_yaml} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pre_script">Pre-script (Optional)</Label>
                                    <Textarea
                                        id="pre_script"
                                        value={data.pre_script}
                                        onChange={(e) => setData('pre_script', e.target.value)}
                                        placeholder="Enter script to run before task execution"
                                        rows={6}
                                        className="font-mono text-sm"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Script that will be executed before the task starts.
                                    </p>
                                    <InputError message={errors.pre_script} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="post_script">Post-script (Optional)</Label>
                                    <Textarea
                                        id="post_script"
                                        value={data.post_script}
                                        onChange={(e) => setData('post_script', e.target.value)}
                                        placeholder="Enter script to run after task execution"
                                        rows={6}
                                        className="font-mono text-sm"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Script that will be executed after the task completes.
                                    </p>
                                    <InputError message={errors.post_script} />
                                </div>

                                <Separator className="my-6" />

                                <div className="space-y-2">
                                    <Label htmlFor="judge_type">Judge Type</Label>
                                    <Select
                                        value={data.judge_type}
                                        onValueChange={(value) => setData('judge_type', value as JudgeType)}
                                    >
                                        <SelectTrigger id="judge_type">
                                            <SelectValue placeholder="Select a judge type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="AiJudge">AI Judge</SelectItem>
                                            <SelectItem value="QuizJudge">Quiz Judge</SelectItem>
                                            <SelectItem value="TextJudge">Text Judge</SelectItem>
                                            <SelectItem value="AutoJudge">Auto Judge</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Choose how this task will be evaluated.
                                    </p>
                                    <InputError message={errors.judge_type} />
                                </div>

                                {/* AI Judge Configuration */}
                                {data.judge_type === 'AiJudge' && (
                                    <div className="space-y-4 rounded-lg border p-4 bg-muted/30">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-sm font-semibold">AI Judge Configuration</h3>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={addAiJudge}
                                            >
                                                <PlusIcon className="mr-2 h-4 w-4" />
                                                Add AI Judge Entry
                                            </Button>
                                        </div>
                                        {data.ai_judges.map((entry, index) => (
                                            <div key={index} className="space-y-3 rounded-md border p-4 bg-background">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium">Entry {index + 1}</span>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => removeAiJudge(index)}
                                                    >
                                                        <Trash2Icon className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`ai_prompt_${index}`}>Prompt</Label>
                                                    <Textarea
                                                        id={`ai_prompt_${index}`}
                                                        value={entry.prompt}
                                                        onChange={(e) => updateAiJudge(index, 'prompt', e.target.value)}
                                                        placeholder="Enter AI prompt template"
                                                        rows={3}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`ai_question_${index}`}>Question</Label>
                                                    <Textarea
                                                        id={`ai_question_${index}`}
                                                        value={entry.question}
                                                        onChange={(e) => updateAiJudge(index, 'question', e.target.value)}
                                                        placeholder="Enter the question to be evaluated"
                                                        rows={2}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`ai_answer_${index}`}>Expected Answer</Label>
                                                    <Textarea
                                                        id={`ai_answer_${index}`}
                                                        value={entry.answer}
                                                        onChange={(e) => updateAiJudge(index, 'answer', e.target.value)}
                                                        placeholder="Enter the expected answer"
                                                        rows={2}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                        {data.ai_judges.length === 0 && (
                                            <p className="text-sm text-muted-foreground text-center py-4">
                                                No AI judge entries yet. Click the button above to add one.
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Quiz Judge Configuration */}
                                {data.judge_type === 'QuizJudge' && (
                                    <div className="space-y-4 rounded-lg border p-4 bg-muted/30">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-sm font-semibold">Quiz Judge Configuration</h3>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={addQuizQuestion}
                                            >
                                                <PlusIcon className="mr-2 h-4 w-4" />
                                                Add Quiz Question
                                            </Button>
                                        </div>
                                        {data.quiz_questions.map((quiz, qIndex) => (
                                            <div key={qIndex} className="space-y-3 rounded-md border p-4 bg-background">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium">Question {qIndex + 1}</span>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => removeQuizQuestion(qIndex)}
                                                    >
                                                        <Trash2Icon className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`quiz_question_${qIndex}`}>Question</Label>
                                                    <Textarea
                                                        id={`quiz_question_${qIndex}`}
                                                        value={quiz.question}
                                                        onChange={(e) => updateQuizQuestion(qIndex, e.target.value)}
                                                        placeholder="Enter quiz question"
                                                        rows={2}
                                                    />
                                                </div>
                                                <div className="space-y-3">
                                                    <div className="flex items-center justify-between">
                                                        <Label>Options</Label>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => addQuizOption(qIndex)}
                                                        >
                                                            <PlusIcon className="mr-2 h-3 w-3" />
                                                            Add Option
                                                        </Button>
                                                    </div>
                                                    {quiz.options.map((option, oIndex) => (
                                                        <div key={oIndex} className="flex items-start gap-2">
                                                            <Checkbox
                                                                id={`quiz_option_correct_${qIndex}_${oIndex}`}
                                                                checked={option.is_correct}
                                                                onCheckedChange={(checked) =>
                                                                    updateQuizOption(qIndex, oIndex, 'is_correct', checked as boolean)
                                                                }
                                                                className="mt-3"
                                                            />
                                                            <div className="flex-1">
                                                                <Input
                                                                    value={option.text}
                                                                    onChange={(e) =>
                                                                        updateQuizOption(qIndex, oIndex, 'text', e.target.value)
                                                                    }
                                                                    placeholder={`Option ${oIndex + 1}`}
                                                                />
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => removeQuizOption(qIndex, oIndex)}
                                                            >
                                                                <Trash2Icon className="h-4 w-4 text-destructive" />
                                                            </Button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                        {data.quiz_questions.length === 0 && (
                                            <p className="text-sm text-muted-foreground text-center py-4">
                                                No quiz questions yet. Click the button above to add one.
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Text Judge Configuration */}
                                {data.judge_type === 'TextJudge' && (
                                    <div className="space-y-4 rounded-lg border p-4 bg-muted/30">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-sm font-semibold">Text Judge Configuration</h3>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={addTextJudge}
                                            >
                                                <PlusIcon className="mr-2 h-4 w-4" />
                                                Add Text Judge Entry
                                            </Button>
                                        </div>
                                        {data.text_judges.map((entry, index) => (
                                            <div key={index} className="space-y-3 rounded-md border p-4 bg-background">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium">Entry {index + 1}</span>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => removeTextJudge(index)}
                                                    >
                                                        <Trash2Icon className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`text_question_${index}`}>Question</Label>
                                                    <Textarea
                                                        id={`text_question_${index}`}
                                                        value={entry.question}
                                                        onChange={(e) => updateTextJudge(index, 'question', e.target.value)}
                                                        placeholder="Enter the question"
                                                        rows={2}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`text_answer_${index}`}>Expected Answer</Label>
                                                    <Textarea
                                                        id={`text_answer_${index}`}
                                                        value={entry.answer}
                                                        onChange={(e) => updateTextJudge(index, 'answer', e.target.value)}
                                                        placeholder="Enter the expected answer"
                                                        rows={2}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                        {data.text_judges.length === 0 && (
                                            <p className="text-sm text-muted-foreground text-center py-4">
                                                No text judge entries yet. Click the button above to add one.
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Auto Judge Configuration */}
                                {data.judge_type === 'AutoJudge' && (
                                    <div className="space-y-4 rounded-lg border p-4 bg-muted/30">
                                        <h3 className="text-sm font-semibold">Auto Judge Configuration</h3>
                                        <div className="space-y-2">
                                            <Label htmlFor="judge_script">Judge Script</Label>
                                            <Textarea
                                                id="judge_script"
                                                value={data.judge_script}
                                                onChange={(e) => setData('judge_script', e.target.value)}
                                                placeholder="Enter script to automatically evaluate the task"
                                                rows={10}
                                                className="font-mono text-sm"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                This script will automatically evaluate the task submission.
                                            </p>
                                            <InputError message={errors.judge_script} />
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-6 lg:col-span-1">
                                <div className="space-y-2">
                                    <Label htmlFor="score">Score</Label>
                                    <Input
                                        id="score"
                                        type="number"
                                        value={data.score}
                                        onChange={(e) =>
                                            setData('score', parseInt(e.target.value))
                                        }
                                        placeholder="Enter task score"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Higher scores indicate more difficult tasks.
                                    </p>
                                    <InputError message={errors.score} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="is_active">Active</Label>
                                    <div className="flex items-center gap-3">
                                        <Switch
                                            id="is_active"
                                            checked={data.is_active}
                                            onCheckedChange={(checked) =>
                                                setData('is_active', checked)
                                            }
                                            required
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            Toggle to publish or hide this task
                                        </span>
                                    </div>
                                    <InputError message={errors.is_active} />
                                </div>

                                <Separator />

                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Updating...' : 'Update Task'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href="/tasks">Cancel</Link>
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}