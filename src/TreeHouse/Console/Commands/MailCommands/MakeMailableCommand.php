<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\MailCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use InvalidArgumentException;

/**
 * Make Mailable Command
 * 
 * Generate a new Mailable class for sending emails with templates.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\MailCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MakeMailableCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('make:mailable')
             ->setDescription('Generate a new Mailable class')
             ->setHelp('This command allows you to create a new Mailable class for sending templated emails.')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the Mailable class')
             ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'The email template to use')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file if it exists');
    }

    /**
     * Execute the command
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $template = $input->getOption('template');
        $force = $input->getOption('force');

        // Validate class name
        if (!$this->isValidClassName($name)) {
            $this->error($output, "Invalid class name: {$name}");
            return 1;
        }

        // Generate file paths
        $className = $this->getClassName($name);
        $filePath = $this->getFilePath($className);
        $templatePath = $template ?? $this->getDefaultTemplatePath($className);

        // Check if file exists
        if (file_exists($filePath) && !$force) {
            $this->error($output, "Mailable {$className} already exists. Use --force to overwrite.");
            return 1;
        }

        // Create directory if it doesn't exist
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Generate the Mailable class
        $content = $this->generateMailableClass($className, $templatePath);

        // Write the file
        if (file_put_contents($filePath, $content) !== false) {
            $this->success($output, "Mailable {$className} created successfully.");
            $this->info($output, "File: {$filePath}");
            
            if ($template) {
                $this->info($output, "Template: {$templatePath}");
            } else {
                $this->warn($output, "Don't forget to create the email template: resources/views/{$templatePath}.th.html");
            }
            
            return 0;
        } else {
            $this->error($output, "Failed to create Mailable {$className}");
            return 1;
        }
    }

    /**
     * Check if the class name is valid
     *
     * @param string $name
     * @return bool
     */
    private function isValidClassName(string $name): bool
    {
        return preg_match('/^[A-Z][a-zA-Z0-9_]*$/', $name) === 1;
    }

    /**
     * Get the class name from input
     * 
     * @param string $name
     * @return string
     */
    private function getClassName(string $name): string
    {
        // Ensure the name ends with 'Email' or 'Mail'
        if (!str_ends_with($name, 'Email') && !str_ends_with($name, 'Mail')) {
            $name .= 'Email';
        }

        return $name;
    }

    /**
     * Get the file path for the Mailable class
     * 
     * @param string $className
     * @return string
     */
    private function getFilePath(string $className): string
    {
        $basePath = getcwd();
        return "{$basePath}/src/App/Mail/{$className}.php";
    }

    /**
     * Get the default template path
     * 
     * @param string $className
     * @return string
     */
    private function getDefaultTemplatePath(string $className): string
    {
        // Convert ClassName to snake_case
        $templateName = strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($className)));
        $templateName = trim($templateName, '_');
        
        // Remove 'email' or 'mail' suffix
        $templateName = preg_replace('/(email|mail)$/', '', $templateName);
        $templateName = trim($templateName, '_');
        
        return "emails.{$templateName}";
    }

    /**
     * Generate the Mailable class content
     * 
     * @param string $className
     * @param string $templatePath
     * @return string
     */
    private function generateMailableClass(string $className, string $templatePath): string
    {
        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Mail;

use LengthOfRope\TreeHouse\Mail\Mailable;

/**
 * {$className}
 * 
 * Generated Mailable class for sending templated emails.
 * 
 * @package App\Mail
 */
class {$className} extends Mailable
{
    /**
     * Create a new {$className} instance
     * 
     * @param mixed \$data Email data
     */
    public function __construct(protected mixed \$data = null)
    {
        //
    }

    /**
     * Build the mailable
     * 
     * @return self
     */
    public function build(): self
    {
        return \$this
            ->subject('Your Email Subject')
            ->emailTemplate('{$templatePath}', [
                'data' => \$this->data,
                // Add more template variables here
            ]);
    }
}
PHP;

        return $template;
    }
}