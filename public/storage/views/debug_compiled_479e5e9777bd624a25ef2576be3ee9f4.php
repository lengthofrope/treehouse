<?php $this->extend('layouts.app'); ?>
    <?php $this->startSection('content'); ?><div>
        <!-- Dot Notation Examples -->
        <div class="treehouse-card p-6">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Dot Notation Examples</h2>
            
            <!-- Simple Variable Display -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-3">Simple Variables</h3>
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-treehouse-500 mb-3">
                    <p><strong>Page Title:</strong><?php echo thEscape(' ' . ($page['title'])); ?></p>
                    <p><strong>Current Date:</strong><?php echo thEscape(' ' . ($page['date'])); ?></p>
                    <p><strong>Version:</strong><?php echo thEscape(' ' . ($app['version'])); ?></p>
                </div>
                <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded">
                    <strong>Template Code:</strong><br>
                    <code class="text-xs"><p>Page Title: {page.title}</p></code>
                </div>
            </div>

            <!-- Nested Object Access -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-3">Nested Objects</h3>
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-blue-500 mb-3">
                    <p><strong>User Name:</strong><?php echo thEscape(' ' . ($user['profile']['name'])); ?></p>
                    <p><strong>User Email:</strong><?php echo thEscape(' ' . ($user['profile']['email'])); ?></p>
                    <p><strong>User Role:</strong><?php echo thEscape(' ' . ($user['role']['name'])); ?></p>
                    <p><strong>Last Login:</strong><?php echo thEscape(' ' . ($user['activity']['last_login'])); ?></p>
                </div>
                <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded">
                    <strong>Template Code:</strong><br>
                    <code class="text-xs"><p>User Name: {user.profile.name}</p></code>
                </div>
            </div>

            <!-- Configuration Access -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-3">Configuration Values</h3>
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-purple-500 mb-3">
                    <p><strong>App Name:</strong><?php echo thEscape(' ' . ($config['app']['name'])); ?></p>
                    <p><strong>Environment:</strong><?php echo thEscape(' ' . ($config['app']['env'])); ?></p>
                    <p><strong>Debug Mode:</strong><?php echo thEscape(' ' . ($config['app']['debug'])); ?></p>
                    <p><strong>Database Host:</strong><?php echo thEscape(' ' . ($config['database']['host'])); ?></p>
                </div>
                <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded">
                    <strong>Template Code:</strong><br>
                    <code class="text-xs"><p>App Name: {config.app.name}</p></code>
                </div>
            </div>
        </div>

        <!-- th:attr Examples -->
        <div class="treehouse-card p-6">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6">Dynamic Attributes (th:attr)</h2>
            
            <!-- Dynamic Links -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-3">Dynamic Links</h3>
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-green-500 mb-3">
                    <a class="text-treehouse-600 hover:text-treehouse-700 underline" href="<?php echo $user['profile']['url']; ?>"><?php echo thEscape('
                        Visit ' . ($user['profile']['name']) . '\'s Profile
                    '); ?></a><br>
                    <a class="text-blue-600 hover:text-blue-700 underline" href="<?php echo 'mailto:'   $user['profile']['email']; ?>"><?php echo thEscape('
                        Email ' . ($user['profile']['name']) . '
                    '); ?></a>
                </div>
                <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded">
                    <strong>Template Code:</strong><br>
                    <code class="text-xs"><a th:attr="href=user.profile.url">Visit Profile</a></code>
                </div>
            </div>

            <!-- Dynamic Images -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-3">Dynamic Images</h3>
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-yellow-500 mb-3">
                    <img class="w-16 h-16 rounded-full border-2 border-gray-300" src="<?php echo $user['profile']['avatar']; ?>" alt="<?php echo $user['profile']['name']   ' Avatar'; ?>">
                    <p class="mt-2 text-sm"><?php echo thEscape('Profile photo of ' . ($user['profile']['name'])); ?></p>
                </div>
                <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded">
                    <strong>Template Code:</strong><br>
                    <code class="text-xs"><img th:attr="src=user.profile.avatar, alt=user.profile.name + ' Avatar'"></code>
                </div>
            </div>

            <!-- Dynamic Classes -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-3">Dynamic CSS Classes</h3>
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-red-500 mb-3">
                    <div class="<?php echo 'p-3 rounded-lg '   $user['status']['css_class']; ?>">
                        <p><strong>Status:</strong><?php echo thEscape(' ' . ($user['status']['name'])); ?></p>
                        <p><strong>Badge:</strong> <span class="<?php echo 'px-2 py-1 rounded text-xs '   $user['badge']['css_class']; ?>"><?php echo thEscape(($user['badge']['text'])); ?></span></p>
                    </div>
                </div>
                <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded">
                    <strong>Template Code:</strong><br>
                    <code class="text-xs"><div th:attr="class='p-3 rounded-lg ' + user.status.css_class"></code>
                </div>
            </div>

            <!-- Dynamic Data Attributes -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-800 mb-3">Data Attributes</h3>
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-indigo-500 mb-3">
                    <button class="bg-treehouse-600 text-white px-4 py-2 rounded hover:bg-treehouse-700 transition-colors" onclick="<?php echo 'showUser('   $user['id']   ')'; ?>">
                        View User Details
                    </button>
                </div>
                <div class="text-sm text-gray-600 bg-gray-100 p-3 rounded">
                    <strong>Template Code:</strong><br>
                    <code class="text-xs"><button th:attr="data-user-id=user.id, onclick='showUser(' + user.id + ')'"></code>
                </div>
            </div>
        </div>
 
        <!-- Advanced Examples -->
        <div class="mt-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">Advanced Template Features</h2>
            
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Conditional Rendering -->
                <div class="treehouse-card p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Conditional Rendering</h3>
                    
                    <div class="space-y-4">
                        <?php if ($user['is_premium']): ?><div class="bg-yellow-100 border border-yellow-300 p-4 rounded-lg">
                            <h4 class="font-medium text-yellow-800">Premium User</h4>
                            <p class="text-yellow-700"><?php echo thEscape('Welcome, ' . ($user['profile']['name']) . '! You have premium access.'); ?></p>
                        </div><?php endif; ?>
                        
                        <?php if (!($user['is_premium'])): ?><div class="bg-blue-100 border border-blue-300 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-800">Standard User</h4>
                            <p class="text-blue-700"><?php echo thEscape('Hi ' . ($user['profile']['name']) . ', upgrade to premium for more features!'); ?></p>
                        </div><?php endif; ?>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-600 bg-gray-100 p-3 rounded">
                        <strong>Template Code:</strong><br>
                        <code class="text-xs"><div th:if="user.is_premium">Premium content</div></code>
                    </div>
                </div>

                <!-- Loop Example -->
                <div class="treehouse-card p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Loops with Dot Notation</h3>
                    
                    <div class="space-y-2">
                        <h4 class="font-medium text-gray-800">User's Recent Activities:</h4>
                        <div class="bg-gray-50 p-3 rounded border-l-4 border-gray-300">
                            <div class="flex justify-between items-center">
                                <span class="font-medium"><?php echo thEscape(($activity['type'])); ?></span>
                                <span class="text-sm text-gray-500"><?php echo thEscape(($activity['date'])); ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1"><?php echo thEscape(($activity['description'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-sm text-gray-600 bg-gray-100 p-3 rounded">
                        <strong>Template Code:</strong><br>
                        <code class="text-xs"><div th:repeat="activity user.activities">{activity.type}</div></code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sample Data Display -->
        <div class="mt-12 treehouse-card p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">Sample Data Structure</h3>
            <p class="text-gray-600 mb-4">This page demonstrates data access using the following sample structure:</p>
            
            <div class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto">
                <pre class="text-sm"><code>{
    "user": {
    "id": 123,
    "profile": {
        "name": "John Doe",
        "email": "john@example.com",
        "avatar": "/images/avatars/john.jpg",
        "url": "/users/123"
    },
    "role": {
        "name": "Admin",
        "permissions": ["read", "write", "delete"]
    },
    "status": {
        "name": "Active",
        "css_class": "bg-green-100 text-green-800"
    },
    "badge": {
        "text": "VIP",
        "css_class": "bg-purple-100 text-purple-800"
    },
    "is_premium": true,
    "activity": {
        "last_login": "2024-06-29 15:30:00"
    },
    "activities": [
        {
        "type": "Login",
        "description": "Logged in from Chrome browser",
        "date": "2 minutes ago"
        },
        {
        "type": "Profile Update",
        "description": "Updated profile picture",
        "date": "1 hour ago"
        }
    ]
    },
    "config": {
    "app": {
        "name": "TreeHouse Demo",
        "env": "development",
        "debug": true
    },
    "database": {
        "host": "localhost"
    }
    },
    "page": {
    "title": "Templating Engine Demo",
    "date": "June 29, 2025"
    },
    "app": {
    "version": "1.0.0"
    }
    }</code></pre>
    </div>
</div></div><?php $this->endSection(); ?>