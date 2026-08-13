<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('repairs a seo opportunities table whose original migration was recorded without adding the column', function () {
    Schema::drop('seo_opportunities');
    Schema::create('seo_opportunities', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('website_id');
    });

    try {
        expect(Schema::hasColumn('seo_opportunities', 'content_request_id'))->toBeFalse();

        $migration = require database_path('migrations/2026_08_13_172116_repair_content_request_id_on_seo_opportunities_table.php');
        $migration->up();

        expect(Schema::hasColumn('seo_opportunities', 'content_request_id'))->toBeTrue()
            ->and(Schema::hasIndex('seo_opportunities', 'seo_opportunities_content_request_unique', 'unique'))->toBeTrue();
    } finally {
        Schema::drop('seo_opportunities');
        (require database_path('migrations/2026_08_13_100954_create_seo_opportunities_table.php'))->up();
        (require database_path('migrations/2026_08_13_113053_add_content_request_id_to_seo_opportunities_table.php'))->up();
    }
});

it('is safe when the repaired column already exists', function () {
    $migration = require database_path('migrations/2026_08_13_172116_repair_content_request_id_on_seo_opportunities_table.php');

    $migration->up();

    expect(Schema::hasColumn('seo_opportunities', 'content_request_id'))->toBeTrue();
});
