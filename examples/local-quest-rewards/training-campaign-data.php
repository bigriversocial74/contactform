<?php
 declare(strict_types=1);

/**
 * Static Training Campaign Lab seed data.
 *
 * Phase 1 intentionally uses PHP seed arrays so the UI shell can be built
 * before the SQL schema and persistence layer are added.
 */
function tcl_campaigns(): array
{
    return [
        '5-day-movement-challenge' => [
            'id' => '5-day-movement-challenge',
            'title' => '5-Day Movement Challenge',
            'eyebrow' => 'Fitness / Wellness',
            'type' => 'Fitness',
            'status' => 'active',
            'difficulty' => 'Beginner',
            'visibility' => 'Public',
            'description' => 'Complete a daily movement sequence, upload photo or video proof, and unlock rewards for verified consistency.',
            'short_description' => 'Move daily for 5 days. Upload proof and build a streak.',
            'image_hint' => 'Movement',
            'reward_preview' => '$15 Wellness Microgift',
            'next_reward' => '$5 smoothie reward',
            'sequence_count' => 1,
            'task_count' => 4,
            'participant_count' => 124,
            'duration' => '5 days',
            'streak' => 3,
            'progress' => 60,
            'points' => 30,
            'next_points' => 50,
            'tags' => ['Fitness', 'Streak', 'Video Proof'],
            'sequence' => [
                'title' => 'Daily Movement Routine',
                'description' => 'A repeatable four-step movement sequence with proof upload at each step.',
                'steps' => [
                    ['title' => 'Warm-Up', 'status' => 'completed', 'proof' => 'Photo or video', 'points' => 10, 'description' => 'Complete a 5–10 minute warm-up and upload proof.'],
                    ['title' => 'Movement Session', 'status' => 'current', 'proof' => 'Video proof', 'points' => 15, 'description' => 'Complete your main movement activity for at least 20 minutes.'],
                    ['title' => 'Cool Down', 'status' => 'pending', 'proof' => 'Photo or checklist', 'points' => 10, 'description' => 'Stretch or recover after your movement session.'],
                    ['title' => 'Reflection', 'status' => 'locked', 'proof' => 'Text note', 'points' => 15, 'description' => 'Submit a short note about how the session went.'],
                ],
            ],
            'reward_ladder' => [
                ['label' => 'First Sequence', 'requirement' => 'Complete 1 routine', 'reward' => 'Entry badge', 'status' => 'unlocked'],
                ['label' => '3 Verified Routines', 'requirement' => 'Complete 3 routines', 'reward' => '$5 Microgift', 'status' => 'current'],
                ['label' => '5-Day Streak', 'requirement' => 'Complete 5 days', 'reward' => '$15 Microgift', 'status' => 'locked'],
                ['label' => '20 Completions', 'requirement' => 'Complete 20 total', 'reward' => 'Sponsor bonus', 'status' => 'locked'],
            ],
        ],
        'coffee-shop-opening-routine' => [
            'id' => 'coffee-shop-opening-routine',
            'title' => 'Coffee Shop Opening Routine',
            'eyebrow' => 'Merchant / Staff Training',
            'type' => 'Merchant',
            'status' => 'active',
            'difficulty' => 'Easy',
            'visibility' => 'Team',
            'description' => 'Verify daily store-readiness actions with photos, checklist steps, and manager review.',
            'short_description' => 'Complete daily opening tasks and submit photo proof.',
            'image_hint' => 'Opening',
            'reward_preview' => '$10 Team Reward',
            'next_reward' => '$2 opening bonus',
            'sequence_count' => 1,
            'task_count' => 5,
            'participant_count' => 96,
            'duration' => 'Weekday routine',
            'streak' => 5,
            'progress' => 80,
            'points' => 40,
            'next_points' => 50,
            'tags' => ['Merchant', 'Team', 'Photo Proof'],
            'sequence' => [
                'title' => 'Daily Opening Checklist',
                'description' => 'A store readiness sequence for teams and shift managers.',
                'steps' => [
                    ['title' => 'Clean Counter Photo', 'status' => 'completed', 'proof' => 'Photo proof', 'points' => 10, 'description' => 'Upload a photo of the clean counter and register area.'],
                    ['title' => 'Stocked Pastry Case', 'status' => 'completed', 'proof' => 'Photo proof', 'points' => 10, 'description' => 'Upload a photo of stocked display items.'],
                    ['title' => 'Espresso Startup', 'status' => 'current', 'proof' => 'Short video', 'points' => 15, 'description' => 'Upload a short startup proof clip.'],
                    ['title' => 'QR Table Tent', 'status' => 'pending', 'proof' => 'Photo proof', 'points' => 10, 'description' => 'Confirm QR/table tent placement is visible.'],
                    ['title' => 'Manager Approval', 'status' => 'locked', 'proof' => 'Manager review', 'points' => 15, 'description' => 'Manager verifies the opening sequence.'],
                ],
            ],
            'reward_ladder' => [
                ['label' => 'One Opening', 'requirement' => 'Complete one opening', 'reward' => '$2 reward', 'status' => 'unlocked'],
                ['label' => '5-Day Routine', 'requirement' => 'Complete 5 days', 'reward' => '$10 team reward', 'status' => 'current'],
                ['label' => 'Perfect Month', 'requirement' => 'No missed days', 'reward' => '$50 team reward', 'status' => 'locked'],
            ],
        ],
        '14-day-creator-practice-streak' => [
            'id' => '14-day-creator-practice-streak',
            'title' => '14-Day Creator Practice Streak',
            'eyebrow' => 'Creator / Consistency',
            'type' => 'Creator',
            'status' => 'active',
            'difficulty' => 'Medium',
            'visibility' => 'Public',
            'description' => 'Encourage daily creator practice through short uploads, proof receipts, and streak-based rewards.',
            'short_description' => 'Upload daily creation practice for 14 days straight.',
            'image_hint' => 'Creator',
            'reward_preview' => 'Exclusive Badge + Reward',
            'next_reward' => 'Creator badge',
            'sequence_count' => 1,
            'task_count' => 3,
            'participant_count' => 38,
            'duration' => '14 days',
            'streak' => 7,
            'progress' => 50,
            'points' => 25,
            'next_points' => 50,
            'tags' => ['Creator', 'Consistency', 'Daily Upload'],
            'sequence' => [
                'title' => 'Daily Practice Routine',
                'description' => 'A daily creative practice streak with lightweight proof uploads.',
                'steps' => [
                    ['title' => 'Practice Clip', 'status' => 'completed', 'proof' => 'Video proof', 'points' => 15, 'description' => 'Upload a 30-second practice clip.'],
                    ['title' => 'Practice Note', 'status' => 'current', 'proof' => 'Text note', 'points' => 10, 'description' => 'Submit a short note about today’s practice.'],
                    ['title' => 'Daily Completion', 'status' => 'pending', 'proof' => 'Checklist', 'points' => 10, 'description' => 'Confirm the daily routine is complete.'],
                ],
            ],
            'reward_ladder' => [
                ['label' => '5 Days', 'requirement' => 'Complete 5 days', 'reward' => 'Fan badge', 'status' => 'unlocked'],
                ['label' => '10 Days', 'requirement' => 'Complete 10 days', 'reward' => 'Microgift reward', 'status' => 'current'],
                ['label' => '14-Day Streak', 'requirement' => 'Complete all days', 'reward' => 'Sponsor reward', 'status' => 'locked'],
            ],
        ],
    ];
}

function tcl_campaign(string $campaignId): ?array
{
    $campaigns = tcl_campaigns();
    return $campaigns[$campaignId] ?? null;
}

function tcl_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Active',
        'draft' => 'Draft',
        'paused' => 'Paused',
        'completed' => 'Completed',
        'current' => 'In Progress',
        'pending' => 'Pending',
        'locked' => 'Locked',
        'unlocked' => 'Unlocked',
        default => ucwords(str_replace(['_', '-'], ' ', $status)),
    };
}

function tcl_status_class(string $status): string
{
    return match ($status) {
        'active', 'completed', 'unlocked' => 'is-success',
        'current', 'pending' => 'is-info',
        'locked', 'draft' => 'is-muted',
        'paused' => 'is-warning',
        default => 'is-muted',
    };
}
