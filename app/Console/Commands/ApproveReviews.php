<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Review;

class ApproveReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:approve {--all : Approve all pending reviews}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve pending reviews';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $count = Review::where('is_approved', false)->update(['is_approved' => true]);
            $this->info("Approved {$count} reviews.");
        } else {
            $reviews = Review::where('is_approved', false)->get();
            
            if ($reviews->isEmpty()) {
                $this->info('No pending reviews.');
                return;
            }

            foreach ($reviews as $review) {
                $this->info("Review from {$review->user->name} for {$review->product->name}:");
                $this->line("Rating: {$review->rating}/5");
                $this->line("Comment: {$review->comment}");
                
                if ($this->confirm('Approve this review?')) {
                    $review->update(['is_approved' => true]);
                    $this->info('Review approved.');
                }
                
                $this->line('---');
            }
        }
    }
}