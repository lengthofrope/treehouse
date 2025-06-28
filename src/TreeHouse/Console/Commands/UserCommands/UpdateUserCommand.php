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
 * Update User Command
 * 
 * Updates an existing user account via the command line interface.
 * Supports updating name, email, password, role, and verification status.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\UserCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class UpdateUserCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('user:update')
            ->setDescription('Update an existing user account')
            ->setHelp('This command allows you to update user account information including name, email, password and role.')
            ->addArgument('identifier', InputArgument::REQUIRED, 'User ID or email address')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Update user name')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Update email address')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Update password')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'Update user role (admin, editor, viewer)')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Mark email as verified')
            ->addOption('unverify', null, InputOption::VALUE_NONE, 'Mark email as unverified')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Use interactive mode');
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
            $identifier = $input->getArgument('identifier');
            
            // Find the user
            $user = $this->findUser($identifier);
            if (!$user) {
                $this->error($output, "User with identifier '{$identifier}' not found.");
                return 1;
            }

            // Get updates
            $updates = $this->getUpdates($input, $output, $user);
            
            if (empty($updates)) {
                $this->info($output, 'No updates specified.');
                return 0;
            }

            // Show current user info
            $this->showUserInfo($output, $user, 'Current user information:');
            
            // Confirm updates
            if (!$this->confirmUpdates($output, $updates)) {
                $this->info($output, 'Update cancelled.');
                return 0;
            }

            // Apply updates
            if ($this->updateUser($user['id'], $updates, $output)) {
                $this->success($output, "User '{$user['name']}' updated successfully!");
                
                // Show updated info
                $updatedUser = $this->findUser($user['id']);
                if ($updatedUser) {
                    $this->showUserInfo($output, $updatedUser, 'Updated user information:');
                }
                
                return 0;
            } else {
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error($output, "Failed to update user: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Get database connection
     */
    private function getDatabaseConnection(): Connection
    {
        Env::loadIfNeeded();
        
        $config = [
            'driver' => Env::get('DB_CONNECTION', Env::get('DB_DRIVER', 'mysql')),
            'host' => Env::get('DB_HOST', 'localhost'),
            'port' => (int) Env::get('DB_PORT', 3306),
            'database' => Env::get('DB_DATABASE', ''),
            'username' => Env::get('DB_USERNAME', ''),
            'password' => Env::get('DB_PASSWORD', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
        ];
        
        return new Connection($config);
    }

    /**
     * Find user by ID or email
     */
    private function findUser(string $identifier): ?array
    {
        $connection = $this->getDatabaseConnection();
        
        // Try by ID first (if numeric)
        if (is_numeric($identifier)) {
            $result = $connection->select(
                'SELECT id, name, email, role, email_verified, email_verified_at, created_at FROM users WHERE id = ?',
                [(int) $identifier]
            );
        } else {
            $result = $connection->select(
                'SELECT id, name, email, role, email_verified, email_verified_at, created_at FROM users WHERE email = ?',
                [$identifier]
            );
        }
        
        return $result[0] ?? null;
    }

    /**
     * Get updates from input or interactive prompts
     */
    private function getUpdates(InputInterface $input, OutputInterface $output, array $user): array
    {
        $updates = [];
        
        if ($input->getOption('interactive')) {
            return $this->getInteractiveUpdates($input, $output, $user);
        }

        // Get updates from options
        if ($name = $input->getOption('name')) {
            $updates['name'] = $name;
        }

        if ($email = $input->getOption('email')) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error($output, 'Invalid email address format.');
                return [];
            }
            
            if ($this->emailExistsForOtherUser($email, $user['id'])) {
                $this->error($output, "Email '{$email}' is already in use by another user.");
                return [];
            }
            
            $updates['email'] = $email;
        }

        if ($password = $input->getOption('password')) {
            if (strlen($password) < 6) {
                $this->error($output, 'Password must be at least 6 characters.');
                return [];
            }
            $updates['password'] = $password;
        }

        if ($role = $input->getOption('role')) {
            $availableRoles = ['admin', 'editor', 'viewer'];
            if (!in_array($role, $availableRoles)) {
                $this->error($output, "Invalid role '{$role}'. Available roles: " . implode(', ', $availableRoles));
                return [];
            }
            $updates['role'] = $role;
        }

        if ($input->getOption('verify')) {
            $updates['email_verified'] = true;
        } elseif ($input->getOption('unverify')) {
            $updates['email_verified'] = false;
        }

        return $updates;
    }

    /**
     * Get updates through interactive prompts
     */
    private function getInteractiveUpdates(InputInterface $input, OutputInterface $output, array $user): array
    {
        $updates = [];
        
        $this->info($output, 'Interactive user update mode...');
        $output->writeln('');

        // Name
        $newName = $this->ask($output, 'New name (leave empty to keep current)', $user['name']);
        if ($newName !== $user['name']) {
            $updates['name'] = $newName;
        }

        // Email
        $newEmail = $this->ask($output, 'New email (leave empty to keep current)', $user['email']);
        if ($newEmail !== $user['email']) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $this->error($output, 'Invalid email address format.');
                return [];
            }
            
            if ($this->emailExistsForOtherUser($newEmail, $user['id'])) {
                $this->error($output, "Email '{$newEmail}' is already in use by another user.");
                return [];
            }
            
            $updates['email'] = $newEmail;
        }

        // Password
        if ($this->confirm($output, 'Update password?', false)) {
            $password = $this->askForPassword($output);
            if ($password) {
                $updates['password'] = $password;
            }
        }

        // Role
        $availableRoles = ['admin', 'editor', 'viewer'];
        $this->comment($output, 'Available roles: ' . implode(', ', $availableRoles));
        $newRole = $this->ask($output, 'New role (leave empty to keep current)', $user['role']);
        if ($newRole !== $user['role']) {
            if (!in_array($newRole, $availableRoles)) {
                $this->warn($output, "Invalid role '{$newRole}', keeping current role.");
            } else {
                $updates['role'] = $newRole;
            }
        }

        // Email verification
        $currentVerified = $user['email_verified'] ? 'verified' : 'unverified';
        if ($this->confirm($output, "Email is currently {$currentVerified}. Change verification status?", false)) {
            $updates['email_verified'] = !$user['email_verified'];
        }

        return $updates;
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
        
        $output->write('<question>New password:</question> ');
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        $output->writeln('');

        if (strlen($password) < 6) {
            $this->error($output, 'Password must be at least 6 characters');
            return $this->askForPassword($output);
        }

        $output->write('<question>Confirm password:</question> ');
        system('stty -echo');
        $confirmPassword = trim(fgets(STDIN));
        system('stty echo');
        $output->writeln('');

        if ($password !== $confirmPassword) {
            $this->error($output, 'Passwords do not match');
            return $this->askForPassword($output);
        }

        return $password;
    }

    /**
     * Check if email exists for another user
     */
    private function emailExistsForOtherUser(string $email, int $userId): bool
    {
        try {
            $connection = $this->getDatabaseConnection();
            $result = $connection->select(
                'SELECT id FROM users WHERE email = ? AND id != ?',
                [$email, $userId]
            );
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Show user information
     */
    private function showUserInfo(OutputInterface $output, array $user, string $title): void
    {
        $this->info($output, $title);
        $output->writeln("  ID: {$user['id']}");
        $output->writeln("  Name: {$user['name']}");
        $output->writeln("  Email: {$user['email']}");
        $output->writeln("  Role: {$user['role']}");
        $output->writeln("  Verified: " . ($user['email_verified'] ? 'Yes' : 'No'));
        $output->writeln("  Created: {$user['created_at']}");
        $output->writeln('');
    }

    /**
     * Confirm updates with user
     */
    private function confirmUpdates(OutputInterface $output, array $updates): bool
    {
        $this->comment($output, 'The following updates will be applied:');
        
        foreach ($updates as $field => $value) {
            if ($field === 'password') {
                $output->writeln("  - {$field}: [hidden]");
            } elseif ($field === 'email_verified') {
                $output->writeln("  - {$field}: " . ($value ? 'verified' : 'unverified'));
            } else {
                $output->writeln("  - {$field}: {$value}");
            }
        }
        
        $output->writeln('');
        return $this->confirm($output, 'Apply these updates?', true);
    }

    /**
     * Update user in database
     */
    private function updateUser(int $userId, array $updates, OutputInterface $output): bool
    {
        try {
            $connection = $this->getDatabaseConnection();
            
            // Build update query
            $fields = [];
            $values = [];
            
            foreach ($updates as $field => $value) {
                if ($field === 'password') {
                    $hash = new Hash();
                    $fields[] = 'password = ?';
                    $values[] = $hash->make($value);
                } elseif ($field === 'email_verified') {
                    $fields[] = 'email_verified = ?';
                    $values[] = $value ? 1 : 0;
                    
                    if ($value) {
                        $fields[] = 'email_verified_at = ?';
                        $values[] = date('Y-m-d H:i:s');
                    } else {
                        $fields[] = 'email_verified_at = ?';
                        $values[] = null;
                    }
                } else {
                    $fields[] = "{$field} = ?";
                    $values[] = $value;
                }
            }
            
            // Add updated_at
            $fields[] = 'updated_at = ?';
            $values[] = date('Y-m-d H:i:s');
            
            // Add user ID for WHERE clause
            $values[] = $userId;
            
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
            
            $affectedRows = $connection->update($sql, $values);
            
            return $affectedRows > 0;
            
        } catch (\Exception $e) {
            $this->error($output, "Database error: {$e->getMessage()}");
            return false;
        }
    }
}