<?php

declare(strict_types=1);

namespace App\Controllers;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\View\ViewFactory;

class HomeController
{
    private ViewFactory $view;
    
    public function index(): Response
    {
        $content = view('home', [
            'title' => 'Welcome to TreeHouse',
            'message' => 'Your TreeHouse application is running successfully!',
            'showHero' => true, 
        ])->render();
        
        return new Response($content);
    }
    
    public function about(): Response
    {
        $content = view('about', [
            'title' => 'About TreeHouse',
            'message' => 'TreeHouse is a modern PHP framework built for rapid development.'
        ])->render();
        
        return new Response($content);
    }
}