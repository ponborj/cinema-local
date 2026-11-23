<?php

namespace Tests\Feature;

use App\Http\Controllers\ConversionController;
use App\Jobs\PrepareVideo;
use App\Models\MediaConversion;
use App\Services\TvConversions;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->temporaryDirectory = storage_path('framework/testing/cinema_'.Str::uuid());
        File::ensureDirectoryExists($this->temporaryDirectory.'/media/Série');
        file_put_contents($this->temporaryDirectory.'/media/Série/Episódio 01.mkv', '0123456789');
        file_put_contents($this->temporaryDirectory.'/media/Série/Episódio 01.pt.srt', "1\n00:00:00,000 --> 00:00:01,000\nOlá, TV!\n");
        file_put_contents($this->temporaryDirectory.'/media/segredo.txt', 'private');
        file_put_contents($this->temporaryDirectory.'/fora.mp4', 'outside');
        config(['media.directory' => $this->temporaryDirectory.'/media', 'media.converted_directory' => $this->temporaryDirectory.'/converted']);
    }

    protected function tearDown(): void
    {
        $parent = realpath(storage_path('framework/testing'));
        $target = realpath($this->temporaryDirectory);
        if ($parent && $target && str_starts_with($target, $parent.DIRECTORY_SEPARATOR.'cinema_')) {
            File::deleteDirectory($target);
        }
        parent::tearDown();
    }

    public function test_blade_catalog_lists_folders_and_videos_and_escapes_names(): void
    {
        file_put_contents($this->temporaryDirectory.'/media/Filme & Amigos.mp4', 'test');
        $this->get('/')->assertOk()->assertSee('Filme &amp; Amigos', false)->assertDontSee('Filme & Amigos', false);
    }

    public function test_catalog_and_unicode_subfolders_only_expose_media(): void
    {
        $this->get('/')->assertOk()->assertSee('Série')->assertDontSee('segredo.txt');
        $this->get('/?folder='.rawurlencode('Série'))->assertOk()->assertSee('Episódio 01')->assertDontSee('pt.srt');
    }

    public function test_range_streaming_returns_only_requested_bytes(): void
    {
        $response = $this->withHeader('Range', 'bytes=2-5')->get('/media?path='.rawurlencode('Série/Episódio 01.mkv'));
        $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10')->assertHeader('Content-Length', '4');
        ob_start();
        $response->baseResponse->sendContent();
        $contents = ob_get_clean();
        $this->assertSame('2345', $contents);
    }

    public function test_suffix_and_head_requests_are_supported(): void
    {
        $url = '/media?path='.rawurlencode('Série/Episódio 01.mkv');
        $response = $this->withHeader('Range', 'bytes=-3')->get($url)->assertStatus(206)->assertHeader('Content-Range', 'bytes 7-9/10');
        ob_start();
        $response->baseResponse->sendContent();
        $contents = ob_get_clean();
        $this->assertSame('789', $contents);
        $this->flushHeaders();
        $this->head($url)->assertOk()->assertHeader('Content-Length', '10');
        $this->withHeader('Range', 'bytes=100-')->get($url)->assertStatus(416)->assertHeader('Content-Range', 'bytes */10');
    }

    public function test_traversal_absolute_paths_and_non_media_are_blocked(): void
    {
        foreach (['../fora.mp4', '..\\fora.mp4', 'G:/fora.mp4', '/etc/passwd', 'segredo.txt'] as $path) {
            $this->get('/media?path='.rawurlencode($path))->assertForbidden();
        }
        $this->getJson('/media?path[]=anything')->assertUnprocessable();
        $this->get('/.env')->assertNotFound();
    }

    public function test_public_clients_and_unknown_hosts_are_blocked(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'attacker.example', 'SERVER_NAME' => 'attacker.example'])->get('http://attacker.example/')->assertForbidden();
    }

    public function test_srt_subtitles_become_utf8_webvtt(): void
    {
        $response = $this->get('/subtitle?path='.rawurlencode('Série/Episódio 01.pt.srt'))->assertOk()->assertHeader('Content-Type', 'text/vtt; charset=utf-8');
        $this->assertStringStartsWith("WEBVTT\n\n", $response->getContent());
        $this->assertStringContainsString('00:00:00.000 --> 00:00:01.000', $response->getContent());
        $this->assertStringContainsString('Olá, TV!', $response->getContent());
    }

    public function test_conversion_posts_reject_missing_csrf_tokens_outside_test_bypass(): void
    {
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $this->postJson('/api/conversions', ['path' => 'Série/Episódio 01.mkv'])->assertStatus(419);
    }

    public function test_queued_conversion_can_be_cancelled_without_writing_an_output(): void
    {
        $key = str_repeat('a', 64);
        MediaConversion::create(['id' => $key, 'path' => 'Série/Episódio 01.mkv', 'status' => 'queued']);
        $this->postJson('/api/conversions/'.$key.'/cancel')->assertOk()->assertJsonPath('state', 'cancelled');
        (new PrepareVideo($key))->handle(app(TvConversions::class));
        $this->assertFileDoesNotExist(app(TvConversions::class)->output($key));
    }

    public function test_real_mkv_conversion_selects_portuguese_audio_reuses_output_and_preserves_original(): void
    {
        $finder = new ExecutableFinder;
        $ffmpeg = is_file(config('media.ffmpeg')) ? config('media.ffmpeg') : $finder->find(config('media.ffmpeg'));
        $ffprobe = is_file(config('media.ffprobe')) ? config('media.ffprobe') : $finder->find(config('media.ffprobe'));
        if (! $ffmpeg || ! $ffprobe) {
            $this->markTestSkipped('Configure FFMPEG_BINARY and FFPROBE_BINARY for the conversion integration test.');
        }
        config(['media.ffmpeg' => $ffmpeg, 'media.ffprobe' => $ffprobe]);
        Queue::fake();
        $source = $this->temporaryDirectory.'/media/Teste.mkv';
        $subtitle = $this->temporaryDirectory.'/media/Série/Episódio 01.pt.srt';
        $generate = new Process([config('media.ffmpeg'), '-v', 'error', '-nostdin', '-f', 'lavfi', '-i', 'testsrc2=size=160x90:rate=24:duration=2', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2', '-f', 'lavfi', '-i', 'sine=frequency=880:duration=2', '-i', $subtitle, '-map', '0:v', '-map', '1:a', '-map', '2:a', '-map', '3:s', '-c:v', 'libx264', '-c:a', 'ac3', '-c:s', 'srt', '-metadata:s:a:0', 'language=eng', '-metadata:s:a:1', 'language=por', $source]);
        $generate->mustRun();
        $originalHash = hash_file('sha256', $source);
        $this->get('/watch?path=Teste.mkv')->assertOk()->assertSee('Preparar MP4 para TV')->assertSee('AC3');
        $response = $this->postJson('/api/conversions', ['path' => 'Teste.mkv'])->assertStatus(202)->assertJsonPath('state', 'queued');
        $key = $response->json('key');
        $this->postJson('/api/conversions', ['path' => 'Teste.mkv'])->assertStatus(202)->assertJsonPath('key', $key);
        Queue::assertPushed(PrepareVideo::class, 1);
        (new PrepareVideo($key))->handle(app(TvConversions::class));
        $this->getJson('/api/conversions/'.$key)->assertOk()->assertJsonPath('state', 'ready')->assertJsonPath('percent', 100);
        $output = app(TvConversions::class)->output($key);
        $inspect = new Process([config('media.ffprobe'), '-v', 'error', '-show_streams', '-of', 'json', $output]);
        $inspect->mustRun();
        $streams = json_decode($inspect->getOutput(), true)['streams'];
        $this->assertSame('h264', $streams[0]['codec_name']);
        $this->assertSame('aac', $streams[1]['codec_name']);
        $this->assertSame(2, $streams[1]['channels']);
        $this->assertSame('por', $streams[1]['tags']['language']);
        $this->assertSame($originalHash, hash_file('sha256', $source));
        $this->withHeader('Range', 'bytes=0-31')->get('/prepared/'.$key)->assertStatus(206)->assertHeader('Content-Length', '32');
        $this->flushHeaders();
        $this->postJson('/api/conversions', ['path' => 'Teste.mkv'])->assertOk()->assertJsonPath('state', 'ready');
        Queue::assertPushed(PrepareVideo::class, 1);
        $this->get('/subtitle?path=Teste.mkv&track=3')->assertOk()->assertSee('WEBVTT')->assertSee('Olá, TV!', false);
        $this->postJson('/api/conversions', ['path' => 'Teste.mkv', 'audio' => 999])->assertUnprocessable();
    }

    public function test_duplicate_job_does_not_reprocess_a_completed_conversion(): void
    {
        $key = str_repeat('b', 64);
        File::ensureDirectoryExists(config('media.converted_directory'));
        $output = app(TvConversions::class)->output($key);
        file_put_contents($output, 'completed-output');
        MediaConversion::create(['id' => $key, 'path' => 'Série/Episódio 01.mkv', 'status' => 'ready', 'percent' => 100]);

        config(['media.ffmpeg' => '/unavailable/ffmpeg']);
        (new PrepareVideo($key))->handle(app(TvConversions::class));

        $this->assertSame('ready', MediaConversion::findOrFail($key)->status);
        $this->assertSame('completed-output', file_get_contents($output));
    }

    public function test_cancellation_does_not_revert_a_conversion_completed_since_it_was_loaded(): void
    {
        $key = str_repeat('c', 64);
        $staleConversion = MediaConversion::create(['id' => $key, 'path' => 'Série/Episódio 01.mkv', 'status' => 'working']);
        MediaConversion::whereKey($key)->update(['status' => 'ready', 'percent' => 100]);

        $response = app(ConversionController::class)->cancel($staleConversion);

        $this->assertSame('ready', $response->getData(true)['state']);
        $this->assertFalse(MediaConversion::findOrFail($key)->cancel_requested);
    }
}
