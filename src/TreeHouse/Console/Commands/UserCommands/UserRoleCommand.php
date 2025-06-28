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
 * User Role Management Command
 * 
 * Manages user roles including assignment, bulk operations, and role listing.
 * Supports both individual and batch role management operations.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\UserCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class UserRoleCommand extends Command
{
    /**
     * Available roles
     */
    private const AVAILABLE_ROLES = ['admin', 'editor', 'viewer'];

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('user:role')
            ->setDescription('Manage user roles')
            ->setHelp('This command allows you to assign, change, or list user roles.')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: assign, list, bulk, or stats')
            ->addArgument('identifier', InputArgument::OPTIONAL, 'User ID or email (required for assign action)')
            ->addArgument('role', InputArgument::OPTIONAL, 'Role to assign (admin, editor, viewer)')
            ->addOption('from-role', null, InputOption::VALUE_OPTIONAL, 'Source role for bulk operations')
            ->addOption('to-role', null, InputOption::VALUE_OPTIONAL, 'Target role for bulk operations')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompts')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (table, json, csv)', 'table');
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
            $action = $input->getArgument('action');
            
            switch ($action) {
                case 'assign':
                    return $this->assignRole($input, $output);
                case 'list':
                    return $this->listUserRoles($input, $output);
                case 'bulk':
                    return $this->bulkRoleChange($input, $output);
                case 'stats':
                    return $this->showRoleStats($input, $output);
                default:
                    $this->error($output, "Unknown action '{$action}'. Available actions: assign, list, bulk, stats");
                    return 1;
            }
            
        } catch (\Exception $e) {
            $this->error($output, "Failed to manage user roles: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Assign role to a specific user
     */
    private function assignRole(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getArgument('identifier');
        $role = $input->getArgument('role');
        
        if (!$identifier) {
            $this->error($output, 'User identifier is required for assign action.');
            return 1;
        }
        
        if (!$role) {
            $this->error($output, 'Role is required for assign action.');
            return 1;
        }
        
        if (!in_array($role, self::AVAILABLE_ROLES)) {
            $this->error($output, "Invalid role '{$role}'. Available roles: " . implode(', ', self::AVAILABLE_ROLES));
            return 1;
        }
        
        // Find user
        $user = $this->findUser($identifier);
        if (!$user) {
            $this->error($output, "User with identifier '{$identifier}' not found.");
            return 1;
        }
        
        // Check if role is already assigned
        if ($user['role'] === $role) {
            $this->info($output, "User '{$user['name']}' already has role '{$role}'.");
            return 0;
        }
        
        $this->info($output, "Assigning role '{$role}' to user '{$user['name']}'");
        $this->comment($output, "Current role: {$user['role']} → New role: {$role}");
        $output->writeln('');
        
        // Confirm if not forced
        if (!$input->getOption('force')) {
            if (!$this->confirm($output, 'Proceed with role assignment?', true)) {
                $this->info($output, 'Role assignment cancelled.');
                return 0;
            }
        }
        
        // Update role
        if ($this->updateUserRole($user['id'], $role, $output)) {
            $this->success($output, "Role '{$role}' assigned to user '{$user['name']}' successfully.");
            return 0;
        }
        
        return 1;
    }

    /**
     * List users by role
     */
    private function listUserRoles(InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        $format = $input->getOption('format');
        
        $users = $connection->select(
            'SELECT id, name, email, role, created_at FROM users ORDER BY role, name'
        );
        
        if (empty($users)) {
            $this->info($output, 'No users found.');
            return 0;
        }
        
        switch ($format) {
            case 'json':
                $output->writeln(json_encode($users, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->outputUserRolesCsv($users, $output);
                break;
            default:
                $this->outputUserRolesTable($users, $output);
                break;
        }
        
        return 0;
    }

    /**
     * Bulk role change operation
     */
    private function bulkRoleChange(InputInterface $input, OutputInterface $output): int
    {
        $fromRole = $input->getOption('from-role');
        $toRole = $input->getOption('to-role');
        
        if (!$fromRole || !$toRole) {
            $this->error($output, 'Both --from-role and --to-role options are required for bulk operations.');
            return 1;
        }
        
        if (!in_array($fromRole, self::AVAILABLE_ROLES) || !in_array($toRole, self::AVAILABLE_ROLES)) {
            $this->error($output, "Invalid role. Available roles: " . implode(', ', self::AVAILABLE_ROLES));
            return 1;
        }
        
        if ($fromRole === $toRole) {
            $this->error($output, 'Source and target roles cannot be the same.');
            return 1;
        }
        
        // Find affected users
        $connection = db();
        $affectedUsers = $connection->select(
            'SELECT id, name, email FROM users WHERE role = ?',
            [$fromRole]
        );
        
        if (empty($affectedUsers)) {
            $this->info($output, "No users found with role '{$fromRole}'.");
            return 0;
        }
        
        $this->warn($output, "Bulk role change: {$fromRole} → {$toRole}");
        $this->comment($output, "Affected users (" . count($affectedUsers) . "):");
        
        foreach ($affectedUsers as $user) {
            $output->writeln("  - {$user['name']} ({$user['email']})");
        }
        
        $output->writeln('');
        
        // Confirm if not forced
        if (!$input->getOption('force')) {
            if (!$this->confirm($output, 'Proceed with bulk role change?', false)) {
                $this->info($output, 'Bulk role change cancelled.');
                return 0;
            }
        }
        
        // Perform bulk update
        $affectedRows = $connection->update(
            'UPDATE users SET role = ?, updated_at = ? WHERE role = ?',
            [$toRole, date('Y-m-d H:i:s'), $fromRole]
        );
        
        $this->success($output, "Successfully updated {$affectedRows} users from '{$fromRole}' to '{$toRole}'.");
        return 0;
    }

    /**
     * Show role statistics
     */
    private function showRoleStats(InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        
        $stats = $connection->select(
            'SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC'
        );
        
        $total = $connection->select('SELECT COUNT(*) as total FROM users')[0]['total'];
        
        $this->info($output, 'User Role Statistics');
        $output->writeln('');
        
        // Header
        $this->outputStatsRow($output, ['Role', 'Count', 'Percentage'], true);
        $output->writeln(str_repeat('-', 40));
        
        // Stats
        foreach ($stats as $stat) {
            $percentage = $total > 0 ? round(($stat['count'] / $total) * 100, 1) : 0;
            $this->outputStatsRow($output, [
                ucfirst($stat['role']),
                $stat['count'],
                $percentage . '%'
            ]);
        }
        
        $output->writeln(str_repeat('-', 40));
        $this->outputStatsRow($output, ['Total', $total, '100%']);
        
        return 0;
    }

    /**
     * Find user by ID or email
     */
    private function findUser(string $identifier): ?array
    {
        $connection = db();
        
        if (is_numeric($identifier)) {
            $result = $connection->select(
                'SELECT id, name, email, role FROM users WHERE id = ?',
                [(int) $identifier]
            );
        } else {
            $result = $connection->select(
                'SELECT id, name, email, role FROM users WHERE email = ?',
                [$identifier]
            );
        }
        
        return $result[0] ?? null;
    }

    /**
     * Update user role
     */
    private function updateUserRole(int $userId, string $role, OutputInterface $output): bool
    {
        try {
            $connection = db();
            
            $affectedRows = $connection->update(
                'UPDATE users SET role = ?, updated_at = ? WHERE id = ?',
                [$role, date('Y-m-d H:i:s'), $userId]
            );
            
            return $affectedRows > 0;
            
        } catch (\Exception $e) {
            $this->error($output, "Database error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Output user roles as table
     */
    private function outputUserRolesTable(array $users, OutputInterface $output): void
    {
        $this->info($output, 'Users by Role');
        $output->writeln('');
        
        // Header
        $this->outputTableRow($output, ['ID', 'Name', 'Email', 'Role', 'Created'], true);
        $output->writeln(str_repeat('-', 80));
        
        // Group by role
        $grouped = [];
        foreach ($users as $user) {
            $grouped[$user['role']][] = $user;
        }
        
        foreach ($grouped as $role => $roleUsers) {
            $this->comment($output, strtoupper($role) . " (" . count($roleUsers) . " users)");
            
            foreach ($roleUsers as $user) {
                $this->outputTableRow($output, [
                    $user['id'],
                    $this->truncate($user['name'], 15),
                    $this->truncate($user['email'], 25),
                    $user['role'],
                    date('Y-m-d', strtotime($user['created_at']))
                ]);
            }
            
            $output->writeln('');
        }
    }

    /**
     * Output user roles as CSV
     */
    private function outputUserRolesCsv(array $users, OutputInterface $output): void
    {
        $output->writeln('ID,Name,Email,Role,Created');
        
        foreach ($users as $user) {
            $row = [
                $user['id'],
                '"' . str_replace('"', '""', $user['name']) . '"',
                '"' . str_replace('"', '""', $user['email']) . '"',
                $user['role'],
                $user['created_at']
            ];
            
            $output->writeln(implode(',', $row));
        }
    }

    /**
     * Output a table row
     */
    private function outputTableRow(OutputInterface $output, array $columns, bool $isHeader = false): void
    {
        $widths = [5, 17, 27, 10, 12];
        $row = '';
        
        foreach ($columns as $index => $column) {
            $width = $widths[$index] ?? 15;
            $formatted = str_pad((string) $column, $width);
            
            if ($isHeader) {
                $row .= "<info>{$formatted}</info>";
            } else {
                $row .= $formatted;
            }
        }
        
        $output->writeln($row);
    }

    /**
     * Output a stats row
     */
    private function outputStatsRow(OutputInterface $output, array $columns, bool $isHeader = false): void
    {
        $widths = [15, 10, 12];
        $row = '';
        
        foreach ($columns as $index => $column) {
            $width = $widths[$index] ?? 15;
            $formatted = str_pad((string) $column, $width);
            
            if ($isHeader) {
                $row .= "<info>{$formatted}</info>";
            } else {
                $row .= $formatted;
            }
        }
        
        $output->writeln($row);
    }

    /**
     * Truncate text to specified length
     */
    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - 3) . '...';
    }
}