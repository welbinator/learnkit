# LearnKit Admin React App

React-powered admin interface for the LearnKit WordPress LMS plugin.

## Development

### Prerequisites
- Node.js 18+ and npm
- WordPress 6.0+
- LearnKit plugin activated

### Setup

```bash
# Install dependencies
npm install

# Start development server (with hot reload)
npm start

# Build for production
npm run build
```

### Build Output

The webpack config outputs the compiled bundle to:
```
../../assets/js/learnkit-admin.js
```

This file is automatically enqueued by the `LearnKit_Admin` class.

## Architecture

- **@wordpress/scripts**: Build tooling (webpack, babel, eslint)
- **@wordpress/components**: UI components (Button, Card, etc.)
- **@wordpress/element**: React abstraction layer
- **@wordpress/i18n**: Internationalization

## API Integration

The React app communicates with the WordPress backend via REST API:

- **Endpoint**: `/wp-json/learnkit/v1/`
- **Authentication**: WordPress REST API nonce (passed via `wp_localize_script`)
- **Global object**: `window.learnkitAdmin` contains API URL, nonce, and user data

## Sprint Roadmap

- **Sprint 1 (Current)**: Hello World + API connection test
- **Sprint 2**: Course CRUD, module/lesson management
- **Sprint 3**: Enrollment interface, progress tracking
- **Sprint 4**: Student dashboard, catalog, certificates

## File Structure

```
src/
├── index.js          # Entry point
├── App.js            # Root component
├── style.css         # Global styles
├── components/       # Reusable components (Sprint 2+)
└── utils/            # Helper functions (Sprint 2+)
```

## Development Workflow

1. Run `npm start` for development with hot reload
2. Make changes to files in `src/`
3. Refresh WordPress admin page to see changes
4. Run `npm run build` before committing for production

## Code Quality

```bash
# Format code
npm run format

# Lint JavaScript
npm run lint:js

# Lint CSS
npm run lint:css
```

All code follows WordPress Coding Standards via @wordpress/scripts.
