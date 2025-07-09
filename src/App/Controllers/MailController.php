<?php

declare(strict_types=1);

namespace App\Controllers;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Mail\Mailable;
use App\Mail\WelcomeEmail;

class MailController
{
    public function index(): Response
    {
        $content = view('mail.index', [
            'title' => 'Mail System Demo',
            'showHero' => false,
        ])->render();
        
        return new Response($content);
    }
    
    public function sendTest(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $email = $request->input('email');
            $subject = $request->input('subject', 'Test Email from TreeHouse');
            $message = $request->input('message', 'This is a test email sent from the TreeHouse framework.');
            
            try {
                // Send simple email
                sendMail($email, $subject, $message);
                
                $content = view('mail.test-result', [
                    'title' => 'Email Sent Successfully',
                    'showHero' => false,
                    'success' => true,
                    'email' => $email,
                    'subject' => $subject,
                    'message' => 'Email sent successfully!'
                ])->render();
            } catch (\Exception $e) {
                $content = view('mail.test-result', [
                    'title' => 'Email Send Failed',
                    'showHero' => false,
                    'success' => false,
                    'email' => $email,
                    'subject' => $subject,
                    'error' => $e->getMessage()
                ])->render();
            }
        } else {
            $content = view('mail.send-test', [
                'title' => 'Send Test Email',
                'showHero' => false,
            ])->render();
        }
        
        return new Response($content);
    }
    
    public function sendTemplated(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $email = $request->input('email');
            $name = $request->input('name', 'User');
            
            try {
                // Create user-like object for demo
                $user = (object)[
                    'name' => $name,
                    'email' => $email,
                    'id' => rand(1000, 9999)
                ];
                
                // Send templated email using Mailable
                $welcomeEmail = new WelcomeEmail($user);
                $welcomeEmail->send($email);
                
                $content = view('mail.templated-result', [
                    'title' => 'Templated Email Sent',
                    'showHero' => false,
                    'success' => true,
                    'email' => $email,
                    'name' => $name,
                    'message' => 'Welcome email sent successfully!'
                ])->render();
            } catch (\Exception $e) {
                $content = view('mail.templated-result', [
                    'title' => 'Templated Email Failed',
                    'showHero' => false,
                    'success' => false,
                    'email' => $email,
                    'name' => $name,
                    'error' => $e->getMessage()
                ])->render();
            }
        } else {
            $content = view('mail.send-templated', [
                'title' => 'Send Templated Email',
                'showHero' => false,
            ])->render();
        }
        
        return new Response($content);
    }
    
    public function queue(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $email = $request->input('email');
            $subject = $request->input('subject', 'Queued Email from TreeHouse');
            $message = $request->input('message', 'This email was queued and will be processed by the mail queue system.');
            $priority = (int) $request->input('priority', 3);
            
            try {
                // Queue email
                queueMail($email, $subject, $message, $priority);
                
                $content = view('mail.queue-result', [
                    'title' => 'Email Queued Successfully',
                    'showHero' => false,
                    'success' => true,
                    'email' => $email,
                    'subject' => $subject,
                    'priority' => $priority,
                    'message' => 'Email queued successfully and will be processed by the queue system!'
                ])->render();
            } catch (\Exception $e) {
                $content = view('mail.queue-result', [
                    'title' => 'Email Queue Failed',
                    'showHero' => false,
                    'success' => false,
                    'email' => $email,
                    'subject' => $subject,
                    'error' => $e->getMessage()
                ])->render();
            }
        } else {
            $content = view('mail.queue', [
                'title' => 'Queue Email',
                'showHero' => false,
            ])->render();
        }
        
        return new Response($content);
    }
    
    public function attachments(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $email = $request->input('email');
            $subject = $request->input('subject', 'Email with Attachments');
            
            try {
                // Generate sample CSV data
                $csvData = "Name,Email,Role\n";
                $csvData .= "John Doe,john@example.com,Admin\n";
                $csvData .= "Jane Smith,jane@example.com,Editor\n";
                $csvData .= "Bob Johnson,bob@example.com,User\n";
                
                // Generate sample JSON data
                $jsonData = json_encode([
                    'system' => 'TreeHouse Framework',
                    'version' => '1.0.0',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'features' => ['Mail System', 'Queue Management', 'Template Engine']
                ], JSON_PRETTY_PRINT);
                
                // Send email with data attachments
                mailer()
                    ->to($email)
                    ->subject($subject)
                    ->html('<h1>Email with Attachments</h1><p>This email includes sample CSV and JSON attachments generated by TreeHouse.</p>')
                    ->attachData($csvData, 'users.csv', ['mime' => 'text/csv'])
                    ->attachData($jsonData, 'system-info.json', ['mime' => 'application/json'])
                    ->send();
                
                $content = view('mail.attachments-result', [
                    'title' => 'Email with Attachments Sent',
                    'showHero' => false,
                    'success' => true,
                    'email' => $email,
                    'subject' => $subject,
                    'message' => 'Email with CSV and JSON attachments sent successfully!'
                ])->render();
            } catch (\Exception $e) {
                $content = view('mail.attachments-result', [
                    'title' => 'Email with Attachments Failed',
                    'showHero' => false,
                    'success' => false,
                    'email' => $email,
                    'subject' => $subject,
                    'error' => $e->getMessage()
                ])->render();
            }
        } else {
            $content = view('mail.attachments', [
                'title' => 'Send Email with Attachments',
                'showHero' => false,
            ])->render();
        }
        
        return new Response($content);
    }
    
    public function queueStatus(): Response
    {
        try {
            $stats = app('mail.queue')->getStats();
            
            $content = view('mail.queue-status', [
                'title' => 'Mail Queue Status',
                'showHero' => false,
                'stats' => $stats,
                'success' => true
            ])->render();
        } catch (\Exception $e) {
            $content = view('mail.queue-status', [
                'title' => 'Mail Queue Status',
                'showHero' => false,
                'error' => $e->getMessage(),
                'success' => false
            ])->render();
        }
        
        return new Response($content);
    }
}