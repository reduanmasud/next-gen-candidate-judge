<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\ServerProvisionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
            'user_id' => auth()->user()->id,
            'status' => 'pending',
        ]);

        $server->save();

        try {
            $this->provisionService->provision($server, $validated['ssh_username'], $validated['ssh_password']);

            return redirect()->route('servers.index')
                ->with('success', 'Server provisioned successfully');
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
        ]);
    }
}

