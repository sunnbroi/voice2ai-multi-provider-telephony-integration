<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\Provider;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $query = Integration::query()->with('provider');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%$q%")
                    ->orWhere('city', 'like', "%$q%")
                    ->orWhere('country', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%")
                    ->orWhere('tag', 'like', "%$q%")
                    ->orWhere('comment', 'like', "%$q%")
                    ->orWhere('telegram_chat_id', 'like', "%$q%")
                    ->orWhere('notify_type', 'like', "%$q%")
                    ->orWhereHas('provider', fn($provider) => $provider->where('name', 'like', "%$q%"));
            });
        }

        return view('integrations.index', [
            'integrations' => $query->orderByDesc('id')->paginate(10),
        ]);
    }

    public function create()
    {
        $providers = Provider::all();
        $allTags = Integration::pluck('tag')
            ->flatMap(fn($tags) => explode(',', $tags))
            ->map(fn($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values();

        return view('integrations.create', compact('providers', 'allTags'));
    }

    /**
     * Получить теги из request
     * @param Request $request
     * @return string
     */
    private function getTagsFromRequest(Request $request): string
    {
        $rawTags = $request->input('tags', '{}');
        return collect(json_decode($rawTags, true))
            ->pluck('value')
            ->unique()
            ->implode(',');
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'api_key' => 'unique:integrations,api_key'
            ],
            [
                'api_key.unique' => 'Такой API ключ уже существует в системе.'
            ]
        );

        $data = $request->all();
        $data['tag'] = $this->getTagsFromRequest($request);
        $data['active'] = $request->has('active');
        $data['active_tg_notify_client'] = $request->has('active_tg_notify_client');
        $data['active_tg_notify_admin'] = $request->has('active_tg_notify_admin');
        Integration::create($data);
        return redirect()->route('integrations.index');
    }

    public function edit(Integration $integration)
    {
        $providers = Provider::all();

        $allTags = Integration::pluck('tag')
            ->flatMap(fn($tags) => explode(',', $tags))
            ->map(fn($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values();

        return view('integrations.create', compact('integration', 'providers', 'allTags'));
    }

    public function update(Request $request, Integration $integration)
    {
        $data = $request->all();
        $data['tag'] = $this->getTagsFromRequest($request);
        $data['active'] = $request->has('active');
        $data['active_tg_notify_client'] = $request->has('active_tg_notify_client');
        $data['active_tg_notify_admin'] = $request->has('active_tg_notify_admin');
        $integration->update($data);
        return redirect()->route('integrations.index');
    }

    public function destroy(Integration $integration)
    {
        $integration->delete();
        return back();
    }
}
