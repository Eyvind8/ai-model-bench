<?php

namespace App\Http\Controllers;

use App\Enums\VoteWinner;
use App\Evaluation\Arena\ArenaService;
use App\Models\PairwiseVote;
use App\Models\Run;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ArenaController extends Controller
{
    public function index(ArenaService $arena): View
    {
        return view('arena.index', [
            'ratings' => $arena->leaderboard(),
            'votes' => PairwiseVote::query()->count(),
        ]);
    }

    public function show(Run $run, ArenaService $arena): View
    {
        return view('arena.show', [
            'run' => $run,
            'pair' => $arena->pick($run),
            'hasCandidates' => $arena->hasCandidates($run),
            'votes' => $arena->votesCount($run),
        ]);
    }

    public function vote(Request $request, Run $run, ArenaService $arena): RedirectResponse
    {
        $data = $request->validate([
            'result_a' => ['required', 'integer'],
            'result_b' => ['required', 'integer'],
            'winner' => ['required', Rule::enum(VoteWinner::class)],
        ]);

        try {
            $arena->vote($run, (int) $data['result_a'], (int) $data['result_b'], VoteWinner::from($data['winner']));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['vote' => $e->getMessage()]);
        }

        return redirect()->route('runs.arena', $run);
    }
}
