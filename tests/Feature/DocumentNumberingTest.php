<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_running_number_cannot_be_allocated_twice_in_a_fiscal_year(): void
    {
        $user = User::factory()->create(['department' => 'สำนักงานปลัด']);
        $first = Document::create(['doc_type' => 'outgoing', 'title' => 'หนึ่ง', 'status' => 'DRAFT', 'created_by' => $user->id]);
        $second = Document::create(['doc_type' => 'outgoing', 'title' => 'สอง', 'status' => 'DRAFT', 'created_by' => $user->id]);
        $service = app(DocumentNumberingService::class);

        $service->allocate($first, $user, 1, 'ยล 77301/1');

        $this->expectException(ValidationException::class);
        $service->allocate($second, $user, 1, 'ยล 77301/1');
    }
}
