<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\ServerProvisionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ServerProvisionController extends Controller
{
    public function __construct(
        protected ServerProvisionService $provisionService,
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $servers = Server::with('user')
            ->latest()
            ->paginate(10);

        return Inertia::render('servers/index', [
            'servers' => $servers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('servers/create');
    }

    /**
     * Store a newly created resource in storage and provision the server.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'ssh_username' => 'required|string|max:255',
            'ssh_password' => 'required|string|min:8',
        ]);

        $server = new Server([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'ssh_username' => $validated['ssh_username'],
            'ssh_password' => $validated['ssh_password'],
            'user_id' => Auth::user()->id,
            'status' => 'pending',
        ]);

        $server->save();

        try {
            $this->provisionService->provision($server, $validated['ssh_username'], $validated['ssh_password']);

            return redirect()->route('servers.show', $server)
                ->with('success', 'Server provisioning started. You can monitor the progress below.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['ssh_password' => 'Failed to provision server: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Server $server): Response
    {
        $server->load('user');

        return Inertia::render('servers/show', [
            'server' => $server,
            'metadata' => $server->getAllMeta(),
            'workflow' => $server->getWorkflowState(),
        ]);
    }

    /**
     * Get real-time status (for polling)
     */
    public function status(Server $server)
    {
        // Get metadata from server
        $metadata = $server->getAllMeta();

        return response()->json([
            'status' => $server->status,
            'provisioned_at' => optional($server->provisioned_at)->toIso8601String(),
            'metadata' => $metadata,
            'current_step' => $metadata['current_step'] ?? null,
        ]);
    }
}

