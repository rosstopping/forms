<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;

it('shows only the current user\'s websites, forms, and submissions in the admin area', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $websiteA = Website::query()->create([
        'user_id' => $ownerA->id,
        'name' => 'Owner A site',
        'is_active' => true,
        'auto_discovered' => false,
        'email_enabled' => false,
        'webhook_enabled' => false,
    ]);

    $websiteB = Website::query()->create([
        'user_id' => $ownerB->id,
        'name' => 'Owner B site',
        'is_active' => true,
        'auto_discovered' => false,
        'email_enabled' => false,
        'webhook_enabled' => false,
    ]);

    $formA = Form::query()->create([
        'website_id' => $websiteA->id,
        'name' => 'Contact form',
        'slug' => 'contact-form',
        'is_active' => true,
        'auto_discovered' => false,
    ]);

    $formB = Form::query()->create([
        'website_id' => $websiteB->id,
        'name' => 'Booking form',
        'slug' => 'booking-form',
        'is_active' => true,
        'auto_discovered' => false,
    ]);

    FormSubmission::query()->create([
        'website_id' => $websiteA->id,
        'form_id' => $formA->id,
        'data' => ['name' => 'Ada'],
    ]);

    FormSubmission::query()->create([
        'website_id' => $websiteB->id,
        'form_id' => $formB->id,
        'data' => ['name' => 'Grace'],
    ]);

    $this->actingAs($ownerA)
        ->get('/admin/websites')
        ->assertSee($websiteA->name)
        ->assertDontSee($websiteB->name);

    $this->actingAs($ownerA)
        ->get('/admin/forms')
        ->assertSee($formA->name)
        ->assertDontSee($formB->name);

    $this->actingAs($ownerA)
        ->get('/admin/form-submissions')
        ->assertSee('Owner A site')
        ->assertDontSee('Owner B site');

    $this->actingAs($ownerA)
        ->get('/admin/websites/'.$websiteB->id)
        ->assertForbidden();
});
