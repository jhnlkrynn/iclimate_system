<?php

namespace App\Http\Controllers;

use App\Models\ChatbotResponse;
use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChatbotResponseController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $role = (string) $request->query('role', '');

        $responses = ChatbotResponse::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('category', 'like', "%{$search}%")
                        ->orWhere('question', 'like', "%{$search}%")
                        ->orWhere('answer', 'like', "%{$search}%")
                        ->orWhere('keywords', 'like', "%{$search}%")
                        ->orWhere('synonyms', 'like', "%{$search}%")
                        ->orWhere('language', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($role !== '', fn ($query) => $query->where(function ($builder) use ($role): void {
                $builder->whereNull('visible_roles')
                    ->orWhereJsonLength('visible_roles', 0)
                    ->orWhereJsonContains('visible_roles', $role);
            }))
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('chatbot-responses.index', [
            'responses' => $responses,
            'search' => $search,
            'status' => $status,
            'role' => $role,
            'roles' => $this->roles(),
            'statuses' => $this->statuses(),
            'languages' => $this->languages(),
        ]);
    }

    public function create(): View
    {
        return view('chatbot-responses.form', [
            'response' => new ChatbotResponse(['status' => ChatbotResponse::STATUS_ACTIVE]),
            'roles' => $this->roles(),
            'statuses' => $this->statuses(),
            'languages' => $this->languages(),
            'action' => route('chatbot-responses.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $response = ChatbotResponse::query()->create($this->validatedData($request));
        SystemAuditLogger::forModel('created', $response, $request, ['module' => 'Chatbot Responses']);

        return redirect()->route('chatbot-responses.index')->with('success', 'Chatbot response created successfully.');
    }

    public function edit(ChatbotResponse $chatbotResponse): View
    {
        return view('chatbot-responses.form', [
            'response' => $chatbotResponse,
            'roles' => $this->roles(),
            'statuses' => $this->statuses(),
            'languages' => $this->languages(),
            'action' => route('chatbot-responses.update', $chatbotResponse),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, ChatbotResponse $chatbotResponse): RedirectResponse
    {
        $chatbotResponse->update($this->validatedData($request));
        SystemAuditLogger::forModel('updated', $chatbotResponse, $request, ['module' => 'Chatbot Responses']);

        return redirect()->route('chatbot-responses.index')->with('success', 'Chatbot response updated successfully.');
    }

    public function destroy(Request $request, ChatbotResponse $chatbotResponse): RedirectResponse
    {
        $id = $chatbotResponse->id;
        $chatbotResponse->delete();
        SystemAuditLogger::record('Deleted Chatbot Response', $request, [
            'record_type' => ChatbotResponse::class,
            'record_id' => $id,
            'module' => 'Chatbot Responses',
        ]);

        return redirect()->route('chatbot-responses.index')->with('success', 'Chatbot response deleted successfully.');
    }

    public function activate(Request $request, ChatbotResponse $chatbotResponse): RedirectResponse
    {
        $chatbotResponse->update(['status' => ChatbotResponse::STATUS_ACTIVE]);
        SystemAuditLogger::forModel('activated', $chatbotResponse, $request, ['module' => 'Chatbot Responses']);

        return back()->with('success', 'Chatbot response activated.');
    }

    public function deactivate(Request $request, ChatbotResponse $chatbotResponse): RedirectResponse
    {
        $chatbotResponse->update(['status' => ChatbotResponse::STATUS_INACTIVE]);
        SystemAuditLogger::forModel('deactivated', $chatbotResponse, $request, ['module' => 'Chatbot Responses']);

        return back()->with('success', 'Chatbot response deactivated.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string'],
            'language' => ['required', Rule::in(array_keys($this->languages()))],
            'keywords' => ['required', 'string', 'max:2000'],
            'synonyms' => ['nullable', 'string', 'max:2000'],
            'visible_roles' => ['nullable', 'array'],
            'visible_roles.*' => ['string', Rule::in(array_keys($this->roles()))],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        $data['keywords'] = collect(explode(',', $data['keywords']))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->unique(fn (string $keyword): string => strtolower($keyword))
            ->values()
            ->all();

        $data['synonyms'] = collect(explode(',', (string) ($data['synonyms'] ?? '')))
            ->map(fn (string $synonym): string => trim($synonym))
            ->filter()
            ->unique(fn (string $synonym): string => strtolower($synonym))
            ->values()
            ->all();

        $data['visible_roles'] = array_values($data['visible_roles'] ?? []);

        return $data;
    }

    private function roles(): array
    {
        return [
            User::ROLE_FARMER => 'Farmers',
            User::ROLE_MAO => 'MAO Personnel / Technicians',
            User::ROLE_IT_EXPERT => 'IT Admins',
        ];
    }

    private function statuses(): array
    {
        return [
            ChatbotResponse::STATUS_ACTIVE => 'Active',
            ChatbotResponse::STATUS_INACTIVE => 'Inactive',
        ];
    }

    private function languages(): array
    {
        return [
            'English' => 'English',
            'Filipino' => 'Filipino',
            'Taglish' => 'Taglish',
        ];
    }
}
