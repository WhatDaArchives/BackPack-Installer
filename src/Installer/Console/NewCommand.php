<?php  namespace BackPack\Installer\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class NewCommand
 * @package BackPack\Installer\Console
 * @author Valentin PRUGNAUD <valentin@whatdafox.com>
 * @url http://www.foxted.com
 */
class NewCommand extends Command {

    /**
     * Configure the command options.
     * @return void
     */
    protected function configure()
    {
        $this->setName('new')
             ->setDescription('Create a new BackPack package template.')
             ->addArgument('name', InputArgument::REQUIRED, "Name of the package.");
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
             ->cleanUp($zipFile);
        $composer = $this->findComposer();
        $commands = array(
            $composer . ' run-script post-install-cmd',
            $composer . ' run-script post-create-project-cmd',
        );
        $process  = new Process(implode(' && ', $commands), $directory, null, null, null);
        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });
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
        $response = \GuzzleHttp\get('https://github.com/foxted/BackPack-Installer/archive/master.zip')->getBody();
        file_put_contents($zipFile, $response);

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     * @param  string $zipFile
     * @param  string $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;
        $archive->open($zipFile);
        $archive->extractTo($directory);
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