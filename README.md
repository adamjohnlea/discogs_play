<h1>Discogs Collection Player</h1>

<p>A PHP web application for browsing and managing your Discogs record collection with a clean, modern interface.</p>

<h2>Features</h2>
<ul>
    <li>User Authentication:
        <ul>
            <li>Secure user registration and login</li>
            <li>Remember me functionality</li>
            <li>Password security with modern hashing</li>
        </ul>
    </li>
    <li>Discogs Integration:
        <ul>
            <li>Personal access token authentication</li>
            <li>Automatic synchronization with your Discogs collection</li>
            <li>Manual refresh option for immediate updates</li>
        </ul>
    </li>
    <li>Smart Caching System:
        <ul>
            <li>24-hour collection cache for optimal performance</li>
            <li>Permanent caching of release data and images</li>
            <li>Manual cache refresh via settings</li>
        </ul>
    </li>
    <li>Browse your Discogs collection with a modern, responsive interface</li>
    <li>View detailed release information including:
        <ul>
            <li>Cover art and additional release images</li>
            <li>Track listings</li>
            <li>Release notes and credits</li>
            <li>Label and format information</li>
        </ul>
    </li>
    <li>Sort your collection by:
        <ul>
            <li>Date added</li>
            <li>Artist name</li>
        </ul>
    </li>
    <li>Filter by Discogs folders</li>
    <li>SEO-friendly URLs</li>
</ul>

<h2>URL Structure</h2>
<p>The application uses clean, SEO-friendly URLs:</p>
<ul>
    <li>Home: <code>/</code></li>
    <li>Authentication: <code>/login</code>, <code>/register</code></li>
    <li>Settings: <code>/settings</code></li>
    <li>Collection view: <code>/folder/{folder-name}</code></li>
    <li>Sorted view: <code>/folder/{folder-name}/sort/{field}/{direction}/page/{number}</code></li>
    <li>Release view: <code>/release/{id}/{artist-name}/{release-title}</code></li>
</ul>

<h2>Technical Stack</h2>
<ul>
    <li>PHP 8.3+</li>
    <li>Twig templating engine</li>
    <li>Bootstrap 5 for styling</li>
    <li>Font Awesome icons</li>
    <li>Laravel Herd for local development</li>
    <li>SQLite for data storage</li>
</ul>

<h2>Installation</h2>
<ol>
    <li>Clone the repository</li>
    <li>Install dependencies:
        <pre><code>composer install</code></pre>
    </li>
    <li>Copy <code>config/config.example.php</code> to <code>config/config.php</code> and update with your settings</li>
    <li>Set up the database:
        <pre><code>./bin/migrate migrate</code></pre>
    </li>
    <li>Ensure the following directories are writable:
        <ul>
            <li><code>cache/</code></li>
            <li><code>public/img/covers/</code></li>
            <li><code>public/img/releases/</code></li>
            <li><code>database/</code></li>
            <li><code>logs/</code></li>
        </ul>
    </li>
</ol>

<h2>Configuration</h2>
<p>The application requires the following configuration in <code>config/config.php</code>:</p>
<ul>
    <li>Application settings:
        <ul>
            <li>Application name</li>
            <li>Environment (development/production)</li>
        </ul>
    </li>
    <li>Cache settings:
        <ul>
            <li>Collection cache duration (default: 24 hours)</li>
        </ul>
    </li>
    <li>Path configurations:
        <ul>
            <li>Templates directory</li>
            <li>Public directory</li>
            <li>Database location</li>
        </ul>
    </li>
</ul>

<h2>Directory Structure</h2>
<pre>
├── cache/              # Twig template cache
├── config/             # Configuration files
├── database/           # SQLite database
├── public/             # Web root
│   ├── img/           # Image storage
│   └── index.php      # Application entry point
├── src/               # Application source code
│   ├── Controllers/   # Application controllers
│   ├── Database/      # Database migrations
│   ├── Functions/     # Helper functions
│   ├── Middleware/    # Application middleware
│   ├── Models/        # Data models
│   ├── Services/      # Service classes
│   └── Utils/         # Utility functions
└── templates/         # Twig templates
    ├── auth/          # Authentication templates
    ├── layouts/       # Base templates
    └── partials/      # Reusable template parts
</pre>

<h2>Development</h2>
<p>The project uses Laravel Herd for local development. To start developing:</p>
<ol>
    <li>Ensure Laravel Herd is installed and running</li>
    <li>Configure a new site in Herd pointing to the project's public directory</li>
    <li>Access the site at <code>http://your-site-name.test</code></li>
</ol>

<h2>Command Line Tools</h2>
<p>The application includes several useful command-line tools:</p>
<ul>
    <li><code>./bin/migrate migrate</code> - Set up or update the database schema</li>
    <li><code>./bin/migrate rollback</code> - Roll back the last database migration</li>
    <li><code>./bin/clear-cache</code> - Clear the release and image cache</li>
    <li><code>./bin/view-logs</code> - View application logs (accepts optional line count and filter parameters)</li>
</ul>

<h2>Credits</h2>
<p>This application uses the following open-source packages:</p>
<ul>
    <li>Twig templating engine</li>
    <li>Bootstrap 5</li>
    <li>Font Awesome</li>
</ul>

<h2>License</h2>
<p>This project is licensed under the MIT License - see the LICENSE file for details.</p>
