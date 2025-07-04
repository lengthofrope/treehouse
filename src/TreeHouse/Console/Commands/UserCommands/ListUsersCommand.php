<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\UserCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Support\Env;

/**
 * List Users Command
 * 
 * Lists all user accounts with filtering and formatting options.
 * Supports role filtering and output format selection.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\UserCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ListUsersCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('user:list')
            ->setDescription('List all user accounts')
            ->setHelp('This command lists all user accounts with optional filtering by role.')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'Filter by role (admin, editor, author, member)')
            ->addOption('verified', null, InputOption::VALUE_NONE, 'Show only verified users')
            ->addOption('unverified', null, InputOption::VALUE_NONE, 'Show only unverified users')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, csv)', 'table')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of users to show', '50');
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
            $users = $this->getUsers($input);
            
            if (empty($users)) {
                $this->info($output, 'No users found matching the criteria.');
                return 0;
            }

            $format = $input->getOption('format');
            
            switch ($format) {
                case 'json':
                    $this->outputJson($users, $output);
                    break;
                case 'csv':
                    $this->outputCsv($users, $output);
                    break;
                default:
                    $this->outputTable($users, $output);
                    break;
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error($output, "Failed to list users: {$e->getMessage()}");
            return 1;
        }
    }


    /**
     * Get users from database with filters
     */
    private function getUsers(InputInterface $input): array
    {
        $connection = db();
        
        $sql = '
            SELECT u.id, u.name, u.email, u.email_verified, u.email_verified_at, u.created_at,
                   GROUP_CONCAT(r.slug ORDER BY r.slug SEPARATOR ", ") as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
        ';
        $params = [];
        $conditions = [];
        
        // Apply role filter
        if ($role = $input->getOption('role')) {
            $conditions[] = 'r.slug = ?';
            $params[] = $role;
        }
        
        // Apply email verification filter
        if ($input->getOption('verified')) {
            $conditions[] = 'email_verified = 1';
        } elseif ($input->getOption('unverified')) {
            $conditions[] = 'email_verified = 0';
        }
        
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= ' GROUP BY u.id, u.name, u.email, u.email_verified, u.email_verified_at, u.created_at';
        $sql .= ' ORDER BY u.created_at DESC';
        
        // Apply limit
        $limit = (int) $input->getOption('limit');
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $connection->select($sql, $params);
    }

    /**
     * Output users as a formatted table
     */
    private function outputTable(array $users, OutputInterface $output): void
    {
        $this->info($output, 'User Accounts');
        $output->writeln('');
        
        // Header
        $this->outputTableRow($output, [
            'ID', 'Name', 'Email', 'Roles', 'Verified', 'Created'
        ], true);
        
        $output->writeln(str_repeat('-', 90));
        
        // Rows
        foreach ($users as $user) {
            $roles = $user['roles'] ?: 'none';
            $this->outputTableRow($output, [
                $user['id'],
                $this->truncate($user['name'], 15),
                $this->truncate($user['email'], 25),
                $this->truncate($roles, 15),
                $user['email_verified'] ? 'Yes' : 'No',
                date('Y-m-d', strtotime($user['created_at']))
            ]);
        }
        
        $output->writeln('');
        $this->comment($output, "Total: " . count($users) . " users");
    }

    /**
     * Output a table row with proper formatting
     */
    private function outputTableRow(OutputInterface $output, array $columns, bool $isHeader = false): void
    {
        $widths = [5, 17, 27, 17, 10, 12];
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
     * Output users as JSON
     */
    private function outputJson(array $users, OutputInterface $output): void
    {
        $output->writeln(json_encode($users, JSON_PRETTY_PRINT));
    }

    /**
     * Output users as CSV
     */
    private function outputCsv(array $users, OutputInterface $output): void
    {
        // Header
        $output->writeln('ID,Name,Email,Roles,Verified,Created');
        
        // Rows
        foreach ($users as $user) {
            $roles = $user['roles'] ?: 'none';
            $row = [
                $user['id'],
                '"' . str_replace('"', '""', $user['name']) . '"',
                '"' . str_replace('"', '""', $user['email']) . '"',
                '"' . str_replace('"', '""', $roles) . '"',
                $user['email_verified'] ? 'Yes' : 'No',
                $user['created_at']
            ];
            
            $output->writeln(implode(',', $row));
        }
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