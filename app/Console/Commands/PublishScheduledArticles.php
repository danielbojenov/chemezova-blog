<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\Article;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Publishes scheduled articles whose date has arrived.
 *
 * Status is what decides whether an article is live, so something has to move a
 * Scheduled article to Published — this is it, run every minute from
 * `routes/console.php`. Without it, a scheduled article stays invisible forever.
 *
 * `published_at` is deliberately left as the editor set it: that date is when the
 * article was meant to go live and is what the public site orders by, so it must not be
 * rewritten to the moment the scheduler happened to run.
 */
#[Signature('articles:publish-scheduled')]
#[Description('Publish scheduled articles whose publication date has passed')]
class PublishScheduledArticles extends Command
{
    public function handle(): int
    {
        $due = Article::query()
            ->where('status', ContentStatus::Scheduled)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        // Saved one at a time rather than in a mass update, so model events fire and
        // anything that later observes a publish (cache busting, a sitemap ping) sees it.
        foreach ($due as $article) {
            $article->update(['status' => ContentStatus::Published]);

            $this->line("Published [{$article->id}] {$article->title}");
        }

        $this->info($due->isEmpty()
            ? 'No scheduled articles were due.'
            : "Published {$due->count()} scheduled article(s).");

        return self::SUCCESS;
    }
}
