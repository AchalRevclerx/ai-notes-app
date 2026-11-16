<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo account with notes on unrelated topics, so semantic search has
 * something to tell apart. Try: "saving for retirement", "server is down",
 * "what should I cook tonight", "trip planning". None of those phrases appear
 * verbatim in the notes.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_EMAIL = 'demo@example.com';

    public const DEMO_PASSWORD = 'password123';

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => self::DEMO_EMAIL],
            ['name' => 'Demo User', 'password' => self::DEMO_PASSWORD],
        );

        $user->notes()->delete();

        foreach (self::NOTES as [$title, $content]) {
            $user->notes()->create(['title' => $title, 'content' => $content]);
        }
    }

    private const NOTES = [
        [
            'Monthly SIP plan',
            'Started a SIP of 5,000 rupees per month in a Nifty 50 index fund on the 5th of every month. '
            .'Goal: build a corpus over 20 years. Step up the SIP amount by 10% every April when salary is revised. '
            .'Do not stop the SIP when markets fall; falling markets buy more units.',
        ],
        [
            'Credit card rules',
            'Always pay the total amount due, never just the minimum due. Interest on the unpaid balance is around 3.5% a month, '
            .'which is over 40% a year. Keep utilisation under 30% of the limit to protect the CIBIL score. Set autopay for the full statement amount.',
        ],
        [
            'Production incident - payments API',
            'At 2:10 AM the payments API started returning 502 errors. Root cause: the database connection pool was exhausted after a deploy '
            .'doubled the worker count. Rolled back at 2:45 AM. Action items: add an alert on pool saturation, load-test deploys that change concurrency, '
            .'write a runbook for on-call.',
        ],
        [
            'Laravel queue tips',
            'Use ShouldQueueAfterCommit so jobs never see uncommitted rows. Set tries and backoff on every job. '
            .'Run queue:work under a process manager and restart workers after each deploy so they load new code. Failed jobs land in failed_jobs; retry with queue:retry.',
        ],
        [
            'Weeknight dal tadka',
            'Pressure cook one cup toor dal with turmeric and salt for 3 whistles. For the tadka heat ghee, add jeera, garlic, dried red chilli, '
            .'chopped onion and tomato, then a pinch of hing. Pour over the dal, finish with coriander and lemon. Ready in 25 minutes, serves three.',
        ],
        [
            'Paneer butter masala',
            'Blend cashews with tomatoes. Cook butter, ginger-garlic paste and the tomato-cashew puree until oil separates. '
            .'Add kashmiri chilli, garam masala, kasuri methi and cream. Add paneer cubes last so they stay soft. Good with naan for guests on the weekend.',
        ],
        [
            'Goa trip in December',
            'Book flights by October, prices double after that. Stay in North Goa for 3 nights (Anjuna, Vagator) and South Goa for 2 nights (Palolem). '
            .'Rent a scooter. Must visit: Fort Aguada, Dudhsagar falls, and a Saturday night market. Budget around 40,000 per person.',
        ],
        [
            'Ladakh road trip checklist',
            'Acclimatise two days in Leh before going higher. Get inner line permits for Nubra and Pangong online. '
            .'Carry a power bank, cash (ATMs are rare), warm layers and Diamox after asking the doctor. Only postpaid SIMs work there.',
        ],
        [
            'Morning workout routine',
            'Twenty minutes, five days a week: 5 minutes of mobility, 3 rounds of 15 squats, 10 push-ups and a 45 second plank, '
            .'then 5 minutes of skipping. Walk 8,000 steps daily. Sleep by 11 PM; workouts are skipped mostly after late nights.',
        ],
        [
            'Doctor visit notes',
            'Vitamin D is low (14 ng/mL). Prescribed 60,000 IU once a week for 8 weeks, then retest. Get 15 minutes of morning sunlight. '
            .'Blood pressure is normal. Next check-up in three months; carry the previous reports.',
        ],
        [
            'Interview prep - backend role',
            'Revise database indexing, transactions and isolation levels, caching with Redis, and queue design. '
            .'Prepare two stories: the payments outage and the search migration. Practise explaining system design out loud, 45 minutes per mock.',
        ],
        [
            'Book notes: Atomic Habits',
            'Habits compound like interest. Make good habits obvious, attractive, easy and satisfying; invert that to break bad ones. '
            .'Focus on systems, not goals. Habit stacking: after I pour my morning tea, I will read two pages.',
        ],
    ];
}
