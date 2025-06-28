<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\UserCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Support\Env;

/**
 * Delete User Command
 * 
 * Deletes an existing user account via the command line interface.
 * Includes safety confirmations and optional force deletion.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\UserCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class DeleteUserCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('user:delete')
            ->setDescription('Delete a user account')
            ->setHelp('This command allows you to delete a user account. Use with caution as this action cannot be undone.')
            ->addArgument('identifier', InputArgument::REQUIRED, 'User ID or email address')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompts')
            ->addOption('soft', null, InputOption::VALUE_NONE, 'Soft delete (mark as deleted instead of removing)');
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
            $force = $input->getOption('force');
            $soft = $input->getOption('soft');
            
            // Find the user
            $user = $this->findUser($identifier);
            if (!$user) {
                $this->error($output, "User with identifier '{$identifier}' not found.");
                return 1;
            }

            // Show user information
            $this->showUserInfo($output, $user);

            // Safety checks and confirmations
            if (!$force) {
                if (!$this->confirmDeletion($output, $user, $soft)) {
                    $this->info($output, 'Deletion cancelled.');
                    return 0;
                }
            }

            // Perform deletion
            if ($soft) {
                $success = $this->softDeleteUser($user['id'], $output);
                $action = 'marked as deleted';
            } else {
                $success = $this->deleteUser($user['id'], $output);
                $action = 'deleted permanently';
            }

            if ($success) {
                $this->success($output, "User '{$user['name']}' has been {$action}.");
                return 0;
            } else {
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error($output, "Failed to delete user: {$e->getMessage()}");
            return 1;
        }
    }


    /**
     * Find user by ID or email
     */
    private function findUser(string $identifier): ?array
    {
        $connection = db();
        
        // Try by ID first (if numeric)
        if (is_numeric($identifier)) {
            $result = $connection->select(
                'SELECT id, name, email, role, email_verified, created_at FROM users WHERE id = ?',
                [(int) $identifier]
            );
        } else {
            $result = $connection->select(
                'SELECT id, name, email, role, email_verified, created_at FROM users WHERE email = ?',
                [$identifier]
            );
        }
        
        return $result[0] ?? null;
    }

    /**
     * Show user information before deletion
     */
    private function showUserInfo(OutputInterface $output, array $user): void
    {
        $this->warn($output, 'User to be deleted:');
        $output->writeln("  ID: {$user['id']}");
        $output->writeln("  Name: {$user['name']}");
        $output->writeln("  Email: {$user['email']}");
        $output->writeln("  Role: {$user['role']}");
        $output->writeln("  Verified: " . ($user['email_verified'] ? 'Yes' : 'No'));
        $output->writeln("  Created: {$user['created_at']}");
        $output->writeln('');
    }

    /**
     * Confirm deletion with user
     */
    private function confirmDeletion(OutputInterface $output, array $user, bool $soft): bool
    {
        $action = $soft ? 'mark as deleted' : 'permanently delete';
        
        $this->warn($output, "You are about to {$action} this user account.");
        
        if (!$soft) {
            $this->error($output, 'WARNING: This action cannot be undone!');
        }
        
        $output->writeln('');
        
        // First confirmation
        if (!$this->confirm($output, "Are you sure you want to {$action} user '{$user['name']}'?", false)) {
            return false;
        }
        
        // Second confirmation for permanent deletion
        if (!$soft) {
            $this->error($output, 'FINAL WARNING: This will permanently delete the user and all associated data!');
            if (!$this->confirm($output, 'Type "DELETE" to confirm permanent deletion:')) {
                $response = $this->ask($output, '');
                return $response === 'DELETE';
            }
        }
        
        return true;
    }

    /**
     * Soft delete user (mark as deleted)
     */
    private function softDeleteUser(int $userId, OutputInterface $output): bool
    {
        try {
            $connection = db();
            
            // Check if soft delete column exists
            $columns = $connection->getTableColumns('users');
            if (!in_array('deleted_at', $columns)) {
                $this->warn($output, 'Soft delete not supported (deleted_at column not found). Using permanent deletion.');
                return $this->deleteUser($userId, $output);
            }
            
            $affectedRows = $connection->update(
                'UPDATE users SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL',
                [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $userId]
            );
            
            if ($affectedRows === 0) {
                $this->error($output, 'User may already be deleted or not found.');
                return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->error($output, "Database error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Permanently delete user
     */
    private function deleteUser(int $userId, OutputInterface $output): bool
    {
        try {
            $connection = db();
            
            $affectedRows = $connection->delete(
                'DELETE FROM users WHERE id = ?',
                [$userId]
            );
            
            if ($affectedRows === 0) {
                $this->error($output, 'User not found or already deleted.');
                return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->error($output, "Database error: {$e->getMessage()}");
            return false;
        }
    }
}