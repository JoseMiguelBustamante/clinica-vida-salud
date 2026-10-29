<?php

namespace Tests\Feature;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class AdminBootstrapCommandTest extends TestCase
{
    public function test_help_describes_private_effects_without_connecting(): void
    {
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'clinic:bootstrap-admin']));
        $help = Artisan::output();
        foreach (['interactive terminal', 'already migrated PostgreSQL', 'Administrador', 'permissions', 'No password arguments', '12 characters', '72 bytes'] as $text) {
            $this->assertStringContainsString($text, $help);
        }
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_existing_inspire_help_remains_database_free(): void
    {
        $this->assertSame(0, Artisan::call('help', ['command_name' => 'inspire']));
        $this->assertStringContainsString('Display an inspiring quote', Artisan::output());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_no_interaction_option_refuses_through_public_artisan_dispatch(): void
    {
        $this->assertSame(1, Artisan::call('clinic:bootstrap-admin', ['--no-interaction' => true]));
        $this->assertSame("An interactive terminal is required; no changes made.\n", Artisan::output());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public function test_noninteractive_refuses_before_any_question_or_connection(): void
    {
        $tester = $this->executeCommand([], false);
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("An interactive terminal is required; no changes made.\n", $tester->getDisplay());
        $this->assertSame('', $tester->getErrorOutput());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_not_normalized_and_never_connects(array $answers): void
    {
        $tester = $this->executeCommand($answers);
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("Invalid administrator input; no changes made.\n", $tester->getDisplay());
        $this->assertSame('', $tester->getErrorOutput());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    public static function invalidInputs(): array
    {
        $valid = ['operator', 'operator@example.test', 'twelve-characters', 'twelve-characters'];
        $cases = [];
        foreach ([null, '', ' operator', 'operator ', "operator\t", "opera\0tor", str_repeat('u', 101), "\u{00a0}operator", "operator\u{0085}"] as $value) {
            $cases[] = [array_replace($valid, [0 => $value])];
        }
        foreach (['bad-email', ' mail@example.test', "mail@example.test\n", str_repeat('a', 244).'@example.test'] as $value) {
            $cases[] = [array_replace($valid, [1 => $value])];
        }
        foreach (['', '12345678901', str_repeat('a', 73), str_repeat('é', 37), "twelve\0characters"] as $value) {
            $cases[] = [array_replace($valid, [2 => $value, 3 => $value])];
        }
        $cases[] = [array_replace($valid, [3 => 'different-password'])];
        $cases[] = [array_replace($valid, [2 => ' twelve-characters', 3 => 'twelve-characters'])];

        return $cases;
    }

    public function test_hidden_input_failure_is_safe_and_does_not_connect(): void
    {
        $tester = $this->executeCommand(['operator', '', new RuntimeException('sensitive terminal detail')]);
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame("Unable to bootstrap administrator; stop and contact the operator.\n", $tester->getDisplay());
        $this->assertSame('', $tester->getErrorOutput());
        $this->assertSame([], $this->app['db']->getConnections());
    }

    private function executeCommand(array $answers, bool $interactive = true): CommandTester
    {
        $this->app->bind(OutputStyle::class, function ($app, array $parameters) use (&$answers) {
            $style = Mockery::mock(OutputStyle::class, [$parameters['input'], $parameters['output']])->makePartial();
            $style->shouldReceive('askQuestion')->andReturnUsing(function (Question $question) use (&$answers) {
                $this->assertFalse($question->isTrimmable());
                $this->assertSame(in_array($question->getQuestion(), ['Password', 'Confirm password'], true), $question->isHidden());
                if ($question->isHidden()) {
                    $this->assertFalse($question->isHiddenFallback());
                }
                $this->assertNotEmpty($answers, 'Unexpected prompt.');
                $answer = array_shift($answers);
                if ($answer instanceof RuntimeException) {
                    throw $answer;
                }

                return $answer;
            });

            return $style;
        });
        $tester = new CommandTester(Artisan::all()['clinic:bootstrap-admin']);
        $tester->execute([], ['interactive' => $interactive, 'capture_stderr_separately' => true]);
        $this->assertSame([], $answers, 'Not all expected prompts were asked.');

        return $tester;
    }
}
