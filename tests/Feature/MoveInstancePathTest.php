<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Source;
use Artisan;
use Tests\TestCase;

class MoveInstancePathTest extends TestCase
{
    public function test_generic(): void
    {
        test_util_migrate_fresh();

        $ins = Instance::factory()->create();
        $ins->refresh();

        $ins->status = 'ct_dw';
        $ins->queue_active = false;
        $ins->save();

        Source::whereId($ins->source_id)->update(['name' => 'word_press']);

        $extra = $ins->extra;

        $random_path = '/tmp/'.str()->random(20).'/';

        Artisan::call('app:move-instance-path --instance_id='.$ins->id.' --path='.$random_path);

        $ins->refresh();

        $this->assertNotEquals($ins->extra, $extra);
    }
}
