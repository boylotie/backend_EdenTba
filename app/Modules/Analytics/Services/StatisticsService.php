<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Content\Models\Content;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrégation des statistiques d'écoute (MOD-12-P1, US-048).
 *
 * Les agrégats sont calculés directement en SQL (compatible SQLite et MySQL)
 * sur la table `listening_events`, strictement anonyme.
 */
final class StatisticsService
{
    /**
     * Rapport de statistiques pour une période donnée.
     *
     * @return array<string, mixed>
     */
    public function report(string $period, int $limit): array
    {
        $days = (int) trim($period, 'd');
        $start = now()->startOfDay()->subDays($days - 1);
        $end = now()->endOfDay();

        $totals = $this->totals($start, $end);
        $byContent = $this->byContent($start, $end, $limit);
        $byPeriod = $this->byPeriod($start, $end);

        return [
            'period' => [
                'days' => $days,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'totals' => $totals,
            'by_content' => $byContent,
            'by_period' => $byPeriod,
            'empty' => $totals['plays'] === 0 && $totals['completions'] === 0,
        ];
    }

    /**
     * @return array{plays: int, completions: int, contents: int}
     */
    private function totals(CarbonInterface $start, CarbonInterface $end): array
    {
        $row = DB::table('listening_events')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end)
            ->selectRaw("COUNT(*) as events, SUM(CASE WHEN event_type = 'play' THEN 1 ELSE 0 END) as plays, SUM(CASE WHEN event_type = 'completed' THEN 1 ELSE 0 END) as completions, COUNT(DISTINCT content_id) as contents")
            ->first();

        return [
            'plays' => (int) ($row->plays ?? 0),
            'completions' => (int) ($row->completions ?? 0),
            'contents' => (int) ($row->contents ?? 0),
        ];
    }

    /**
     * Top contenus écoutés sur la période (classement par nombre d'écoutes).
     *
     * @return list<array{content_id: int, title: string|null, plays: int, completions: int}>
     */
    private function byContent(CarbonInterface $start, CarbonInterface $end, int $limit): array
    {
        /** @var Collection<int, object{content_id: int, plays: int, completions: int}> $rows */
        $rows = DB::table('listening_events')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end)
            ->selectRaw("content_id, SUM(CASE WHEN event_type = 'play' THEN 1 ELSE 0 END) as plays, SUM(CASE WHEN event_type = 'completed' THEN 1 ELSE 0 END) as completions")
            ->groupBy('content_id')
            ->orderByDesc('plays')
            ->orderByDesc('completions')
            ->limit($limit)
            ->get();

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row->content_id;
        }

        /** @var Collection<int, string|null> $titles */
        $titles = Content::query()->whereIn('id', $ids)->pluck('title', 'id');

        $result = [];
        foreach ($rows as $row) {
            $contentId = (int) $row->content_id;

            $result[] = [
                'content_id' => $contentId,
                'title' => $titles->get($contentId),
                'plays' => (int) $row->plays,
                'completions' => (int) $row->completions,
            ];
        }

        return $result;
    }

    /**
     * Évolution quotidienne sur la période (les jours sans événement sont à zéro).
     *
     * @return list<array{date: string, plays: int, completions: int}>
     */
    private function byPeriod(CarbonInterface $start, CarbonInterface $end): array
    {
        /** @var Collection<int, object{date: string, plays: int, completions: int}> $rows */
        $rows = DB::table('listening_events')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end)
            ->selectRaw("DATE(occurred_at) as date, SUM(CASE WHEN event_type = 'play' THEN 1 ELSE 0 END) as plays, SUM(CASE WHEN event_type = 'completed' THEN 1 ELSE 0 END) as completions")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $actual = $rows->keyBy('date');

        $result = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $row = $actual->get($date);

            $result[] = [
                'date' => $date,
                'plays' => $row !== null ? (int) $row->plays : 0,
                'completions' => $row !== null ? (int) $row->completions : 0,
            ];

            $cursor = $cursor->addDay();
        }

        return $result;
    }
}
