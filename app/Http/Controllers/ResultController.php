<?php

namespace App\Http\Controllers;

use App\Models\Result;
use App\Runs\HumanRating;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function show(Result $result): View
    {
        $result->load(['model.provider', 'run', 'datasetItem', 'evaluations']);

        return view('results.show', ['result' => $result, 'definition' => $result->run->definition()]);
    }

    public function rate(Request $request, Result $result, HumanRating $rating): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $rating->rate($result, (int) $data['rating'], $data['comment'] ?? null);

        return back()->with('status', 'Оцінку збережено');
    }
}
