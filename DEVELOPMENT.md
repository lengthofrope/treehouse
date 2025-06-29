# TreeHouse Framework Development Setup

## Frontend Development with Vite and Tailwind CSS

This project now includes a modern frontend development setup with Vite and Tailwind CSS.

### Quick Start

1. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

2. **Start the development server:**
   ```bash
   # Terminal 1: Start Vite dev server
   npm run dev
   
   # Terminal 2: Start TreeHouse PHP server
   ./bin/th serve
   ```

3. **Build for production:**
   ```bash
   npm run build
   ```

### Development Workflow

**Development Mode:**
- Vite dev server runs on `http://localhost:5173`
- TreeHouse PHP server runs on `http://localhost:8000`
- Hot Module Replacement (HMR) for instant updates
- Tailwind CSS with JIT compilation

**Production Mode:**
- Run `npm run build` to generate optimized assets
- Assets are built to `public/build/` directory
- Automatic asset versioning and caching

### File Structure

```
├── resources/
│   ├── css/
│   │   └── app.css          # Main CSS file with Tailwind
│   ├── js/
│   │   └── app.js           # Main JavaScript entry point
│   └── views/
│       └── layouts/
│           └── app.th.html  # Main template with Tailwind classes
├── public/
│   └── build/               # Generated assets (production)
├── package.json             # Node.js dependencies
├── vite.config.js          # Vite configuration
├── tailwind.config.js      # Tailwind CSS configuration
└── postcss.config.js       # PostCSS configuration
```

### Features

**Tailwind CSS:**
- Custom TreeHouse color palette
- Component classes for buttons, cards, etc.
- Responsive design utilities
- Custom animations

**Vite Integration:**
- Fast development server
- Hot Module Replacement
- Automatic dependency detection
- Production optimization

**TreeHouse Integration:**
- TreeHouse JavaScript framework loads alongside Vite
- Logo and favicon assets served automatically
- CSRF protection integrated
- Template engine with Tailwind classes

### Custom Components

The template includes several custom Tailwind components:

```css
.treehouse-card        # White card with shadow and hover effects
.treehouse-btn         # Primary TreeHouse button
.treehouse-btn-secondary # Secondary button
.treehouse-logo        # Logo sizing utility
.animate-fade-in       # Custom fade-in animation
```

### Development URLs

- **Frontend (Vite):** http://localhost:5173
- **Backend (TreeHouse):** http://localhost:8000
- **Assets:** Automatically proxied through Vite dev server

### Production Deployment

1. Build assets: `npm run build`
2. Deploy `public/build/` directory with your application
3. Update template to use production asset URLs

The template automatically detects development vs production and loads the appropriate assets.