<?php

use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test(
    "kanban stage movement updates deal status ordering and closed-state fields",
    function () {
        Carbon::setTestNow(Carbon::parse("2026-03-15 10:00:00"));

        try {
            $user = User::factory()->create();

            $this->actingAs($user);

            $leadFirst = Deal::factory()
                ->for($user)
                ->create([
                    "status" => "lead",
                    "sort_order" => 0,
                    "is_active" => true,
                    "closed_at" => null,
                    "next_follow_up_at" => "2026-03-20",
                    "probability" => 20,
                ]);

            $leadSecond = Deal::factory()
                ->for($user)
                ->create([
                    "status" => "lead",
                    "sort_order" => 1,
                    "is_active" => true,
                    "closed_at" => null,
                    "next_follow_up_at" => "2026-03-21",
                    "probability" => 25,
                ]);

            $existingWon = Deal::factory()
                ->for($user)
                ->create([
                    "status" => "won",
                    "sort_order" => 0,
                    "is_active" => false,
                    "closed_at" => "2026-03-10",
                    "next_follow_up_at" => null,
                    "probability" => 100,
                ]);

            Livewire::test("pages::deals.index")->call(
                "moveDealStage",
                $leadSecond->id,
                "won",
                0,
            );

            $leadFirst->refresh();
            $leadSecond->refresh();
            $existingWon->refresh();

            expect($leadFirst->sort_order)
                ->toBe(0)
                ->and($leadSecond->status)
                ->toBe("won")
                ->and($leadSecond->sort_order)
                ->toBe(0)
                ->and($leadSecond->is_active)
                ->toBeFalse()
                ->and($leadSecond->probability)
                ->toBe(100)
                ->and($leadSecond->next_follow_up_at)
                ->toBeNull()
                ->and($leadSecond->closed_at?->toDateString())
                ->toBe("2026-03-15")
                ->and($existingWon->sort_order)
                ->toBe(1);
        } finally {
            Carbon::setTestNow();
        }
    },
);

test(
    "kanban stage movement can reorder deals within the same stage",
    function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $first = Deal::factory()
            ->for($user)
            ->create([
                "status" => "proposal",
                "sort_order" => 0,
            ]);

        $second = Deal::factory()
            ->for($user)
            ->create([
                "status" => "proposal",
                "sort_order" => 1,
            ]);

        $third = Deal::factory()
            ->for($user)
            ->create([
                "status" => "proposal",
                "sort_order" => 2,
            ]);

        Livewire::test("pages::deals.index")->call(
            "moveDealStage",
            $first->id,
            "proposal",
            2,
        );

        $first->refresh();
        $second->refresh();
        $third->refresh();

        expect($first->sort_order)
            ->toBe(2)
            ->and($second->sort_order)
            ->toBe(0)
            ->and($third->sort_order)
            ->toBe(1);
    },
);

test(
    "kanban stage movement ignores deal ids outside the authenticated scope",
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        $foreignDeal = Deal::factory()
            ->for($otherUser)
            ->create([
                "status" => "lead",
                "sort_order" => 0,
                "is_active" => true,
                "closed_at" => null,
            ]);

        Livewire::test("pages::deals.index")->call(
            "moveDealStage",
            $foreignDeal->id,
            "won",
            0,
        );

        $foreignDeal->refresh();

        expect($foreignDeal->status)
            ->toBe("lead")
            ->and($foreignDeal->sort_order)
            ->toBe(0)
            ->and($foreignDeal->is_active)
            ->toBeTrue()
            ->and($foreignDeal->closed_at)
            ->toBeNull();
    },
);

test("deals index page renders for authenticated users", function () {
    $user = User::factory()->create();

    Deal::factory()
        ->for($user)
        ->create([
            "status" => "lead",
            "sort_order" => 0,
        ]);

    $this->actingAs($user)
        ->get(route("deals.index"))
        ->assertOk()
        ->assertSee("Deals");
});
