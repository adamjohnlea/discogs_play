# Discogs Collection Player

A web application for browsing and managing your Discogs record collection. Built with PHP and modern web technologies.

## Features

- **Modern Interface**:
  - Responsive design for all devices
  - Smart pagination with ellipsis for large collections
  - Smooth animations and transitions
  - Dark theme optimized
  - Beautiful UI with Bootstrap 5

- **OAuth Authentication**:
  - Secure connection to your Discogs account
  - Automatic token refresh handling
  - Clear connection status indicators

- **Collection Management**:
  - View your entire Discogs collection
  - Sort by date added, artist, or title
  - Filter by Discogs folders
  - Adjustable items per page (25/50/100)
  - Persistent view preferences

- **Release Details**:
  - High-quality cover art display
  - Multiple release images in carousel
  - Detailed track listings
  - Release information and credits
  - Label and format details
  - Personal notes and condition ratings
  - Direct links to Discogs

- **Smart Caching System**:
  - 24-hour collection cache for optimal performance
  - Intelligent release data caching
  - Local image caching for fast loading
  - Manual cache refresh option

- **SEO-friendly URLs**:
  - Clean, readable URLs
  - Artist and release names in URLs
  - Proper folder naming in paths
  - Semantic URL structure

## Requirements

- PHP 8.0 or higher
- SQLite 3
- Composer
- Web server (Apache/Nginx) with URL rewriting support

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/discogs_play.git
   cd discogs_play
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy the example environment file and configure it:
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and add your Discogs OAuth credentials:
   ```
   DISCOGS_OAUTH_KEY=your_oauth_consumer_key
   DISCOGS_OAUTH_SECRET=your_oauth_consumer_secret
   DISCOGS_OAUTH_CALLBACK=https://your-domain.com/oauth/callback
   ```

4. Copy the example config file and configure it:
   ```bash
   cp config/config.example.php config/config.php
   ```

5. Set up the database:
   ```bash
   ./bin/migrate migrate
   ```

6. Ensure the following directories are writable by your web server:
   - `cache/`
   - `public/img/covers/`
   - `public/img/releases/`
   - `database/`
   - `logs/`

7. Configure your web server to point to the `public/` directory

## Usage

1. Register for an account on the application
2. Log in to your account
3. Click "Connect with Discogs" on the settings page
4. Authorize the application with your Discogs account
5. Browse your collection!

## Cache Management

The application implements several caching layers:

- **Collection Cache**: Caches your collection list for 24 hours
- **Release Cache**: Caches individual release details
- **Image Cache**: Caches cover art and additional images locally

To clear the cache:
1. Visit the settings page
2. Click "Refresh Collection Data"

## Development

### Directory Structure

- `bin/` - Command line tools
- `cache/` - Cache storage
- `config/` - Configuration files
- `database/` - SQLITE database
- `logs/` - Application logs
- `public/` - Public web root
- `src/` - Application source code
  - `Controllers/` - Application controllers
  - `Database/` - Database migrations and models
  - `Functions/` - Helper functions
  - `Middleware/` - Middleware classes
  - `Models/` - Model classes
  - `Services/` - Service classes
  - `TwigExtensions/` - Twig extensions
  - `Utils/` - Utility classes
- `templates/` - Twig templates
  - `auth/` - Authentication templates
  - `layouts/` - Layout templates
  - `partials/` - Partial templates

### Database Migrations

To create a new migration:
1. Add a new migration class in `src/Database/Migrations/`
2. Register the migration in `bin/migrate`
3. Run `./bin/migrate migrate`

## Security

- All sensitive credentials are stored in `.env` (not in version control)
- OAuth tokens are securely stored in the database
- User passwords are hashed using modern algorithms
- Session management with secure defaults

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
