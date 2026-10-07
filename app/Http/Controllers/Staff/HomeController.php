<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\Remittance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = today()->toDateString();

        $distributions = Distribution::query()->notVoided()->where('created_by', $user->id)->whereDate('created_at', $today);
        $remittances = Remittance::query()->where('recorded_by', $user->id)->whereDate('created_at', $today);

        // Admins confirm everyone's remittances; staff see their own that are waiting.
        $pending = Remittance::query()->pending()->unless($user->isAdmin(), fn ($q) => $q->where('recorded_by', $user->id));

        return Inertia::render('home', [
            'today' => [
                'date' => today()->format('M d, Y'),
                'distributions' => [
                    'count' => (clone $distributions)->count(),
                    'reams' => (int) (clone $distributions)->sum('qty'),
                    'amount' => (float) (clone $distributions)->sum('total_amount'),
                ],
                'remittances' => [
                    'count' => (clone $remittances)->count(),
                    'amount' => (float) (clone $remittances)->sum('amount'),
                ],
                'pending' => [
                    'count' => (clone $pending)->count(),
                    'amount' => (float) (clone $pending)->sum('amount'),
                    'scope' => $user->isAdmin() ? 'all' : 'mine',
                ],
            ],
        ]);
    }
}
