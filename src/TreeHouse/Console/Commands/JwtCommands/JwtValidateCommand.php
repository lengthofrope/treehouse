<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\JwtCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;
use LengthOfRope\TreeHouse\Support\Env;

/**
 * JWT Validate Command
 *
 * Validates JWT tokens via command line interface.
 * Provides detailed validation results and error reporting.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands\JwtCommands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtValidateCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('jwt:validate')
            ->setDescription('Validate a JWT token')
            ->setHelp('This command validates JWT tokens and provides detailed error information.')
            ->addArgument('token', InputArgument::REQUIRED, 'JWT token to validate')
            ->addOption('secret', 's', InputOption::VALUE_OPTIONAL, 'JWT secret key')
            ->addOption('algorithm', 'a', InputOption::VALUE_OPTIONAL, 'JWT algorithm (HS256, RS256, ES256)')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json|table|plain)', 'plain')
            ->addOption('no-issuer', null, InputOption::VALUE_NONE, 'Skip issuer validation (remove iss from required claims)')
            ->addOption('no-audience', null, InputOption::VALUE_NONE, 'Skip audience validation')
            ->addOption('skip-claims', null, InputOption::VALUE_OPTIONAL, 'Skip specific required claims (comma-separated)', '');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $token = $input->getArgument('token');
            if (!$token) {
                $this->error($output, 'Token is required for validation.');
                return 1;
            }

            $config = $this->createJwtConfig($input);
            $validator = new TokenValidator($config);
            
            try {
                $claims = $validator->validate($token);
                $isValid = true;
                $errors = [];
            } catch (\Exception $e) {
                $isValid = false;
                $errors = [$e->getMessage()];
            }
            
            $format = $input->getOption('format');
            $this->outputTokenValidation($output, $token, $isValid, $errors, $format);

            return $isValid ? 0 : 1;
        } catch (\Exception $e) {
            $this->error($output, 'Token validation failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create JWT configuration from input options
     */
    private function createJwtConfig(InputInterface $input): JwtConfig
    {
        $config = [];
        
        if ($secret = $input->getOption('secret')) {
            $config['secret'] = $secret;
        } elseif ($envSecret = Env::get('JWT_SECRET')) {
            $config['secret'] = $envSecret;
        } else {
            $config['secret'] = 'cli-default-secret-key-change-in-production';
        }
        
        if ($algorithm = $input->getOption('algorithm')) {
            $config['algorithm'] = $algorithm;
        } elseif ($envAlgorithm = Env::get('JWT_ALGORITHM')) {
            $config['algorithm'] = $envAlgorithm;
        }

        // Handle required claims configuration
        $requiredClaims = ['iss', 'iat', 'exp', 'nbf', 'sub']; // Default required claims
        $skipClaims = [];
        
        if ($input->getOption('no-issuer')) {
            $requiredClaims = array_diff($requiredClaims, ['iss']);
            $skipClaims[] = 'iss';
        }
        
        if ($skipClaimsOption = $input->getOption('skip-claims')) {
            $claimsToSkip = array_map('trim', explode(',', $skipClaimsOption));
            $requiredClaims = array_diff($requiredClaims, $claimsToSkip);
            $skipClaims = array_merge($skipClaims, $claimsToSkip);
        }
        
        // Load additional JWT configuration from environment
        if (!in_array('iss', $skipClaims) && ($issuer = Env::get('JWT_ISSUER'))) {
            $config['issuer'] = $issuer;
        }
        
        if (!$input->getOption('no-audience') && ($audience = Env::get('JWT_AUDIENCE'))) {
            $config['audience'] = $audience;
        }
        
        if ($ttl = Env::get('JWT_TTL')) {
            $config['ttl'] = (int) $ttl;
        }
        
        if ($leeway = Env::get('JWT_LEEWAY')) {
            $config['leeway'] = (int) $leeway;
        }

        $config['required_claims'] = array_values($requiredClaims);

        return new JwtConfig($config);
    }

    /**
     * Output token validation results
     */
    private function outputTokenValidation(OutputInterface $output, string $token, bool $isValid, array $errors, string $format): void
    {
        switch ($format) {
            case 'json':
                $output->writeln(json_encode([
                    'valid' => $isValid,
                    'errors' => $errors,
                ], JSON_PRETTY_PRINT));
                break;
                
            case 'table':
                if ($isValid) {
                    $this->success($output, 'Token is VALID');
                } else {
                    $this->error($output, 'Token is INVALID');
                    if (!empty($errors)) {
                        $output->writeln('');
                        $this->warn($output, 'Validation errors:');
                        foreach ($errors as $error) {
                            $output->writeln('  - ' . $error);
                        }
                    }
                }
                break;
                
            default:
                $output->writeln($isValid ? 'VALID' : 'INVALID');
                break;
        }
    }
}