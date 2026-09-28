<?php

namespace Tests\Feature;

use App\Models\DocumentRoutePreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentRoutePresetTest extends TestCase
{
    use RefreshDatabase;

    private function documentCreator(): User
    {
        Role::findOrCreate('officer');

        return User::factory()->create()->assignRole('officer');
    }

    public function test_user_can_create_and_update_a_personal_route_preset(): void
    {
        $creator = $this->documentCreator();
        $firstReviewer = User::factory()->create();
        $secondReviewer = User::factory()->create();

        $this->actingAs($creator)->postJson(route('documents.route_presets.store'), [
            'name' => 'เสนอผู้บริหาร',
            'route_user_ids' => [$firstReviewer->id, $secondReviewer->id],
        ])->assertCreated()
            ->assertJsonPath('preset.name', 'เสนอผู้บริหาร')
            ->assertJsonPath('preset.route_user_ids.0', $firstReviewer->id);

        $this->actingAs($creator)->postJson(route('documents.route_presets.store'), [
            'name' => 'เสนอผู้บริหาร',
            'route_user_ids' => [$secondReviewer->id],
        ])->assertOk()
            ->assertJsonPath('preset.route_user_ids.0', $secondReviewer->id);

        $this->assertDatabaseCount('document_route_presets', 1);
        $this->assertDatabaseHas('document_route_presets', [
            'user_id' => $creator->id,
            'name' => 'เสนอผู้บริหาร',
        ]);
    }

    public function test_route_preset_rejects_owner_and_duplicate_reviewers(): void
    {
        $creator = $this->documentCreator();
        $reviewer = User::factory()->create();

        $this->actingAs($creator)->postJson(route('documents.route_presets.store'), [
            'name' => 'เส้นทางไม่ถูกต้อง',
            'route_user_ids' => [$creator->id, $reviewer->id, $reviewer->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['route_user_ids.0', 'route_user_ids.2']);
    }

    public function test_user_cannot_delete_another_users_route_preset(): void
    {
        $owner = $this->documentCreator();
        $otherUser = $this->documentCreator();
        $reviewer = User::factory()->create();
        $preset = DocumentRoutePreset::create([
            'user_id' => $owner->id,
            'name' => 'ของเจ้าของ',
            'route_user_ids' => [$reviewer->id],
        ]);

        $this->actingAs($otherUser)
            ->deleteJson(route('documents.route_presets.destroy', $preset))
            ->assertNotFound();

        $this->assertDatabaseHas('document_route_presets', ['id' => $preset->id]);
    }

    public function test_create_document_page_only_embeds_current_users_presets(): void
    {
        $creator = $this->documentCreator();
        $otherUser = $this->documentCreator();
        $reviewer = User::factory()->create();

        DocumentRoutePreset::create([
            'user_id' => $creator->id,
            'name' => 'เส้นทางของฉัน',
            'route_user_ids' => [$reviewer->id],
        ]);
        DocumentRoutePreset::create([
            'user_id' => $otherUser->id,
            'name' => 'เส้นทางของคนอื่น',
            'route_user_ids' => [$reviewer->id],
        ]);

        $this->actingAs($creator)->get(route('documents.create'))
            ->assertOk()
            ->assertSee('เส้นทางประจำ')
            ->assertSee('เส้นทางของฉัน')
            ->assertDontSee('เส้นทางของคนอื่น');
    }
}
