<?php namespace BackPack\Installer\Console;

use GuzzleHttp\Client;
use ZipArchive;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NewCommand
 * @package BackPack\Installer\Console
 * @author  Valentin PRUGNAUD <valentin@whatdafox.com>
 * @url http://www.foxted.com
 */
class NewCommand extends Command
{

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param null       $name
     * @param Filesystem $filesystem
     */
    public function __construct($name = null, Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        parent::__construct($name);
    }

    /**
     * Configure the command options.
     * @return void
     */
    protected function configure()
    {
        $this->setName('new')
             ->setDescription('Create a new BackPack package template.')
             ->addArgument('name', InputArgument::REQUIRED, "Name of the package.")
             ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Namespace to use.', 'BackPack');
    }

    /**
     * Execute the command.
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->verifyPackageDoesntExist(
            $directory = getcwd() . '/' . $input->getArgument('name'),
            $output
        );
        $output->writeln('<info>Preparing package...</info>');
        $this->download($zipFile = $this->makeFilename())
             ->extract($zipFile, $directory)
             ->rename($directory)
             ->cleanUp($zipFile)
             ->setNamespace($input, $directory)
             ->runComposer($output, $directory);
        $output->writeln('<comment>Package ready!</comment>');
    }

    /**
     * Verify that the package does not already exist.
     * @param  string         $directory
     * @param OutputInterface $output
     */
    protected function verifyPackageDoesntExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            $output->writeln('<error>Package already exists!</error>');
            exit(1);
        }
    }

    /**
     * Generate a random temporary filename.
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd() . '/backpack_' . md5(time() . uniqid()) . '.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     * @param  string $zipFile
     * @return $this
     */
    protected function download($zipFile)
    {
        $client = new Client();
        $response = $client->get('https://github.com/whatdafox/BackPack/archive/master.zip')->getBody();
        file_put_contents($zipFile, $response);

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     * @param  string $zipFile
     * @return $this
     */
    protected function extract($zipFile)
    {
        $archive = new ZipArchive;
        $archive->open($zipFile);
        $archive->extractTo(__DIR__);
        $archive->close();

        return $this;
    }

    /**
     * Clean-up the Zip file.
     * @param  string $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);
        @unlink($zipFile);

        return $this;
    }

    /**
     * Rename the folder
     * @param $directory
     * @return $this
     */
    protected function rename($directory)
    {
        $this->filesystem->move(
            __DIR__ . '/BackPack-master',
            $directory
        );

        return $this;
    }

    /**
     * Set the namespace in Composer.json
     * @param InputInterface $input
     * @param                $directory
     * @return $this
     */
    protected function setNamespace(InputInterface $input, $directory)
    {
        // Read composer.json file
        $json = json_decode($this->filesystem->get($directory . '/composer.json'), JSON_FORCE_OBJECT);

        // Create the autoload section with appropriate namespace
        $json['autoload'] = [
            "psr-4" => [
                $input->getOption('namespace') . '\\' => 'src'
            ]
        ];

        // Keep empty objects as objects
        // @TODO To be improved
        $json['require'] = new \stdClass();
        $json['suggest'] = new \stdClass();
        $json['autoload-dev'] = new \stdClass();
        $json['extra'] = new \stdClass();

        // Write composer.json file
        $this->filesystem->put(
            $directory . '/composer.json',
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $this;
    }

    /**
     * Run composer install command
     * @param OutputInterface $output
     * @param                 $directory
     * @return $this
     */
    protected function runComposer(OutputInterface $output, $directory)
    {
        $composer = $this->findComposer();
        $commands = array(
            $composer . ' install'
        );
        $process  = new Process(implode(' && ', $commands), $directory, null, null, null);
        $process->run();

        return $this;
    }

    /**
     * Get the composer command for the environment.
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }

        return 'composer';
    }

}