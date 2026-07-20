<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Integration;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function index(Request $request)
    {
        $query = Call::with(['integration', 'integration.provider']);

        if ($request->filled('integration_title')) {
            $query->whereHas('integration', fn($q) =>
            $q->where('title', 'like', '%' . $request->integration_title . '%'));
        }

        if ($request->filled('city')) {
            $query->whereHas('integration', fn($q) =>
            $q->where('city', 'like', '%' . $request->city . '%'));
        }

        if ($request->filled('country')) {
            $query->whereHas('integration', fn($q) =>
            $q->where('country', 'like', '%' . $request->country . '%'));
        }

        if ($request->filled('provider')) {
            $query->whereHas('integration.provider', fn($q) =>
            $q->where('name', 'like', '%' . $request->provider . '%'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('phone')) {
            $query->where(function ($q) use ($request) {
                $q->where('from_phone', 'like', '%' . $request->phone . '%')
                    ->orWhere('to_phone', 'like', '%' . $request->phone . '%');
            });
        }

        if ($request->filled('operator')) {
            $query->where('operator_name', 'like', '%' . $request->operator . '%');
        }

        if ($request->filled('min_duration')) {
            $query->where('duration', '>=', (int) $request->min_duration);
        }

        if ($request->filled('max_duration')) {
            $query->where('duration', '<=', (int) $request->max_duration);
        }

        if ($request->filled('tag')) {
            $query->whereHas('integration', fn($q) =>
            $q->where('tag', 'like', '%' . $request->tag . '%'));
        }

        if ($request->filled('comment')) {
            $query->whereHas('integration', fn($q) =>
            $q->where('comment', 'like', '%' . $request->comment . '%'));
        }
        if ($request->filled('date_range')) {
            $dates = explode(' to ', str_replace(' – ', ' to ', $request->date_range));
            $from = $dates[0] ?? null;
            $to = $dates[1] ?? $from;

            if ($from) {
                $query->whereDate('call_time', '>=', $from);
            }
            if ($to) {
                $query->whereDate('call_time', '<=', $to);
            }
        }

        $starredCount = 0;
        if ($request->boolean('starred')) {
            $query->where('starred', true);
            $starredCount = (clone $query)->where('starred', true)->count();
        }

        return view('calls.index', [
            'calls' => $query->orderByDesc('call_time')->paginate(20),
            'filters' => $request->all(),
            'starredCount' => $starredCount,
        ]);
    }

    public function markListened(Call $call)
    {
        $call->update(['listened' => true]);
        return response()->json(['status' => 'ok']);
    }

    public function toggleStar(Call $call)
    {
        $call->update(['starred' => !$call->starred]);
        return response()->json(['starred' => $call->starred]);
    }
    public function autocomplete(Request $request)
    {
        $field = $request->input('field');
        $term = $request->input('q');

        $allowedFields = [
            'integration_title' => 'title',
            'city' => 'city',
            'country' => 'country',
            'provider' => 'provider_id',
            'phone' => 'phone',
            'tag' => 'tag',
            'comment' => 'comment',
        ];

        if (!array_key_exists($field, $allowedFields)) {
            return response()->json([]);
        }

        $column = $allowedFields[$field];

        if ($field === 'provider') {
            $query = \App\Models\Provider::where('name', 'like', "%$term%")->pluck('name')->unique();
        } elseif ($field === 'phone') {
            $query = \App\Models\Call::select('from_phone')
                ->where('from_phone', 'like', "%$term%")
                ->union(
                    \App\Models\Call::select('to_phone')->where('to_phone', 'like', "%$term%")
                )
                ->distinct()
                ->pluck('from_phone');
        } elseif ($field === 'tag') {
            $query = \App\Models\Integration::pluck('tag')
                ->flatMap(fn($str) => explode(',', $str))
                ->map(fn($t) => trim($t))
                ->filter()
                ->unique()
                ->filter(fn($tag) => str_contains(mb_strtolower($tag), mb_strtolower($term)))
                ->values();
        } else {
            $query = \App\Models\Integration::where($column, 'like', "%$term%")
                ->pluck($column)
                ->unique();
        }

        return response()->json(
            $query->map(fn($item) => [$item])->values()
        );
    }
}
