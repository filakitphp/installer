<?php

declare(strict_types = 1);

namespace Filakit\Commands;

use Filakit\Concerns\InteractsWithHerdOrValet;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class NewCommand extends Command
{
    use InteractsWithHerdOrValet;

    /**
     * The Composer instance.
     *
     * @var Composer
     */
    protected $composer;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        //
    }

    /**
     * Determine if the application is using Laravel 11 or newer.
     */
    public function usingLaravelVersionOrNewer(int $usingVersion, string $directory): bool
    {
        $version = json_decode(file_get_contents($directory . '/composer.json'), true)['require']['laravel/framework'];
        $version = str_replace('^', '', $version);
        $version = explode('.', $version)[0];

        return $version >= $usingVersion;
    }

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Create a new FilaKit application')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('v4', null, InputOption::VALUE_NONE, 'Install FilaKit v4')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    /**
     * Interact with the user before validating the input.
     *
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input);

        $output->write(PHP_EOL . '  <fg=red> _                               _
  ______ _ _       _    _ _
  |  ____(_) |     | |  (_) |
  | |__   _| | __ _| | ___| |_
  |  __| | | |/ _` | |/ / | __|
  | |    | | | (_| |   <| | |_
  |_|    |_|_|\__,_|_|\_\_|\__|</>' . PHP_EOL . PHP_EOL);

        $this->ensureExtensionsAreAvailable();

        if (! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: 'The project name is required.',
                validate: function ($value) use ($input) {
                    if (preg_match('/[^\pL\pN\-_.]/', $value) !== 0) {
                        return 'The name may only contain letters, numbers, dashes, underscores, and periods.';
                    }

                    if ($input->getOption('force') !== true) {
                        try {
                            $this->verifyApplicationDoesntExist($this->getInstallationDirectory($value));
                        } catch (RuntimeException $e) {
                            return 'Application already exists.';
                        }
                    }
                },
            ));
        }

        if ($input->getOption('force') !== true) {
            $this->verifyApplicationDoesntExist(
                $this->getInstallationDirectory($input->getArgument('name'))
            );
        }
    }

    /**
     * Ensure that the required PHP extensions are installed.
     *
     *
     * @throws RuntimeException
     */
    private function ensureExtensionsAreAvailable(): void
    {
        $availableExtensions = get_loaded_extensions();
        $missingExtensions   = collect([
            'ctype',
            'filter',
            'hash',
            'mbstring',
            'openssl',
            'session',
            'tokenizer',
        ])->reject(fn ($extension): bool => in_array($extension, $availableExtensions));

        if ($missingExtensions->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            sprintf('The following PHP extensions are required but are not installed: %s', $missingExtensions->join(', ', ', and '))
        );
    }

    /**
     * Verify that the application does not already exist.
     */
    protected function verifyApplicationDoesntExist(string $directory): void
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Get the installation directory.
     */
    protected function getInstallationDirectory(string $name): string
    {
        return $name !== '.' ? getcwd() . '/' . $name : '.';
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = rtrim($input->getArgument('name'), '/\\');

        $directory = $this->getInstallationDirectory($name);

        $this->composer = new Composer(new Filesystem(), $directory);

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $composer  = $this->findComposer();
        $phpBinary = $this->phpBinary();

        $project   = 'jeffersongoncalves/filakit';
        $stability = 'stable';

        if ($input->getOption('v4')) {
            $project   = 'jeffersongoncalves/filakitv4';
            $stability = 'dev';
        }

        $createProjectCommand = $composer . " create-project $project \"$directory\" --stability $stability --remove-vcs --no-scripts";

        $commands = [
            $createProjectCommand,
            $composer . " run post-root-package-install -d \"$directory\"",
            $phpBinary . " \"$directory/artisan\" key:generate --ansi",
        ];

        if ($directory !== '.' && $input->getOption('force')) {
            if (PHP_OS_FAMILY === 'Windows') {
                array_unshift($commands, "(if exist \"$directory\" rd /s /q \"$directory\")");
            } else {
                array_unshift($commands, "rm -rf \"$directory\"");
            }
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $commands[] = "chmod 755 \"$directory/artisan\"";
        }

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful() && $name !== '.') {
            $this->replaceInFile(
                'APP_URL=http://localhost',
                'APP_URL=' . $this->generateAppUrl($name, $directory),
                $directory . '/.env'
            );
        }

        return $process->getExitCode();
    }

    /**
     * Get the composer command for the environment.
     */
    protected function findComposer(): string
    {
        return implode(' ', $this->composer->findComposer());
    }

    /**
     * Get the path to the appropriate PHP binary.
     */
    protected function phpBinary(): string
    {
        $phpBinary = function_exists('Illuminate\Support\php_binary')
            ? \Illuminate\Support\php_binary()
            : (new PhpExecutableFinder())->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }

    /**
     * Run the given commands.
     */
    protected function runCommands(array $commands, InputInterface $input, OutputInterface $output, ?string $workingPath = null, array $env = []): Process
    {
        if (! $output->isDecorated()) {
            $commands = array_map(function (string $value): string {
                if (Str::startsWith($value, ['chmod', 'git', $this->phpBinary() . ' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value . ' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function (string $value): string {
                if (Str::startsWith($value, ['chmod', 'git', $this->phpBinary() . ' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value . ' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }

        $process->run(function ($type, string $line) use ($output): void {
            $output->write('    ' . $line);
        });

        return $process;
    }

    /**
     * Replace the given string in the given file.
     */
    protected function replaceInFile(string | array $search, string | array $replace, string $file): void
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }

    /**
     * Generate a valid APP_URL for the given application name.
     */
    protected function generateAppUrl(string $name, string $directory): string
    {
        if (! $this->isParkedOnHerdOrValet($directory)) {
            return 'http://localhost:8000';
        }

        $hostname = mb_strtolower($name) . '.' . $this->getTld();

        return $this->canResolveHostname($hostname) ? 'http://' . $hostname : 'http://localhost';
    }

    /**
     * Get the TLD for the application.
     */
    protected function getTld(): string
    {
        return $this->runOnValetOrHerd('tld') ?: 'test';
    }

    /**
     * Determine whether the given hostname is resolvable.
     */
    protected function canResolveHostname(string $hostname): bool
    {
        return gethostbyname($hostname . '.') !== $hostname . '.';
    }
}
