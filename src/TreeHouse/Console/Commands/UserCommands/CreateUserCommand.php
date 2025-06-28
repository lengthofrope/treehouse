<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\UserCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Security\Hash;
use LengthOfRope\TreeHouse\Support\Env;

/**
 * Create User Command
 * 
 * Creates a new user account via the command line interface.
 * Supports interactive mode and batch creation with validation.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\UserCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CreateUserCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('user:create')
            ->setDescription('Create a new user account')
            ->setHelp('This command allows you to create a new user account with name, email, password and role.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The user\'s full name')
            ->addArgument('email', InputArgument::OPTIONAL, 'The user\'s email address')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'User role (admin, editor, viewer)', 'viewer')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'User password (will prompt if not provided)')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Use interactive mode')
            ->addOption('verified', null, InputOption::VALUE_NONE, 'Mark email as verified');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateInput($input, $output)) {
            return 1;
        }

        try {
            // Get user data
            $userData = $this->getUserData($input, $output);
            
            if (!$userData) {
                return 1;
            }

            // Create the user
            $user = $this->createUser($userData, $output);
            
            if (!$user) {
                return 1;
            }

            $this->success($output, "User '{$user['name']}' created successfully!");
            $this->info($output, "Email: {$user['email']}");
            $this->info($output, "Role: {$user['role']}");
            $this->info($output, "ID: {$user['id']}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error($output, "Failed to create user: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Get user data from input or interactive prompts
     */
    private function getUserData(InputInterface $input, OutputInterface $output): ?array
    {
        $interactive = $input->getOption('interactive') || 
                      (!$input->getArgument('name') || !$input->getArgument('email'));

        if ($interactive) {
            return $this->getInteractiveUserData($input, $output);
        }

        $password = $input->getOption('password') ?: $this->askForPassword($output);
        
        // Validate password length
        if (strlen($password) < 6) {
            $this->error($output, 'Password must be at least 6 characters');
            return null;
        }
        
        // Validate email format
        $email = $input->getArgument('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error($output, 'Invalid email address format');
            return null;
        }
        
        // Check if email already exists
        if ($this->emailExists($email)) {
            $this->error($output, "User with email '{$email}' already exists");
            return null;
        }
        
        // Validate role
        $role = $input->getOption('role');
        $availableRoles = ['admin', 'editor', 'viewer'];
        if (!in_array($role, $availableRoles)) {
            $this->error($output, "Invalid role '{$role}'. Available roles: " . implode(', ', $availableRoles));
            return null;
        }

        return [
            'name' => $input->getArgument('name'),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'email_verified' => $input->getOption('verified'),
        ];
    }

    /**
     * Get user data through interactive prompts
     */
    private function getInteractiveUserData(InputInterface $input, OutputInterface $output): ?array
    {
        $this->info($output, 'Creating a new user account...');
        $output->writeln('');

        // Get name
        $name = $input->getArgument('name') ?: $this->ask($output, 'User name');
        if (empty($name)) {
            $this->error($output, 'Name is required');
            return null;
        }

        // Get email
        $email = $input->getArgument('email') ?: $this->ask($output, 'Email address');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error($output, 'Valid email address is required');
            return null;
        }

        // Check if email already exists
        if ($this->emailExists($email)) {
            $this->error($output, "User with email '{$email}' already exists");
            return null;
        }

        // Get role
        $availableRoles = ['admin', 'editor', 'viewer'];
        $defaultRole = $input->getOption('role');
        $this->comment($output, 'Available roles: ' . implode(', ', $availableRoles));
        $role = $this->ask($output, 'User role', $defaultRole);
        
        if (!in_array($role, $availableRoles)) {
            $this->warn($output, "Invalid role '{$role}', using 'viewer'");
            $role = 'viewer';
        }

        // Get password
        $password = $input->getOption('password') ?: $this->askForPassword($output);

        // Ask about email verification
        $verified = $input->getOption('verified') || 
                   $this->confirm($output, 'Mark email as verified?', false);

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'email_verified' => $verified,
        ];
    }

    /**
     * Ask for password with confirmation
     */
    private function askForPassword(OutputInterface $output): string
    {
        // Return a default password in testing environment
        if ($this->isTestingEnvironment()) {
            return 'defaultpassword123';
        }
        
        $output->write('<question>Password:</question> ');
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        $output->writeln('');

        $output->write('<question>Confirm password:</question> ');
        system('stty -echo');
        $confirmPassword = trim(fgets(STDIN));
        system('stty echo');
        $output->writeln('');

        if ($password !== $confirmPassword) {
            $this->error($output, 'Passwords do not match');
            return $this->askForPassword($output);
        }
        
        if (strlen($password) < 6) {
            $this->error($output, 'Password must be at least 6 characters');
            return $this->askForPassword($output);
        }

        return $password;
    }


    /**
     * Check if email already exists
     */
    private function emailExists(string $email): bool
    {
        try {
            $connection = db();
            $result = $connection->select(
                'SELECT id FROM users WHERE email = ?',
                [$email]
            );
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create the user in the database
     */
    private function createUser(array $userData, OutputInterface $output): ?array
    {
        try {
            $connection = db();
            
            // Hash the password
            $hash = new Hash();
            $hashedPassword = $hash->make($userData['password']);
            
            // Prepare user data
            $now = date('Y-m-d H:i:s');
            $emailVerifiedAt = $userData['email_verified'] ? $now : null;
            
            // Insert user
            $connection->insert(
                'INSERT INTO users (name, email, password, role, email_verified, email_verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userData['name'],
                    $userData['email'],
                    $hashedPassword,
                    $userData['role'],
                    $userData['email_verified'] ? 1 : 0,
                    $emailVerifiedAt,
                    $now,
                    $now
                ]
            );
            
            // Get the created user
            $user = $connection->select(
                'SELECT id, name, email, role, email_verified, created_at FROM users WHERE email = ?',
                [$userData['email']]
            );
            
            return $user[0] ?? null;
            
        } catch (\Exception $e) {
            $this->error($output, "Database error: {$e->getMessage()}");
            return null;
        }
    }
}