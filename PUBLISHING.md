# Publishing to Packagist.org

This guide will help you publish your BOG Payment package so others can use it.

## Step 1: Prepare GitHub Repository

### 1.1 Create GitHub Repository

1. Go to [GitHub](https://github.com)
2. Click "New repository"
3. Repository name: `bog-payment` (or your preferred name)
4. Description: "Laravel package for BOG (Bank of Georgia) payment gateway integration"
5. Choose **Public** (required for free Packagist)
6. **DON'T** initialize with README, .gitignore, or license
7. Click "Create repository"

### 1.2 Initialize Git in Your Package

```bash
cd packages/bog-payment

# Initialize git
git init

# Add all files
git add .

# Make initial commit
git commit -m "Initial commit - BOG Payment Package v1.0.0"

# Add remote repository
git remote add origin git@github.com:YOUR_USERNAME/bog-payment.git

# Push to GitHub
git branch -M main
git push -u origin main
```

Replace `YOUR_USERNAME` with your GitHub username.

## Step 2: Final Package Preparation

### 2.1 Update composer.json

Make sure your `composer.json` is complete:

```json
{
    "name": "yourusername/bog-payment",
    "description": "Laravel package for BOG (Bank of Georgia) payment gateway integration",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.email@example.com"
        }
    ],
    "version": "1.0.0",
    "keywords": [
        "laravel",
        "bog",
        "payment",
        "gateway",
        "bank-of-georgia"
    ],
    "require": {
        "php": "^8.0|^8.1|^8.2|^8.3",
        "illuminate/support": "^9.0|^10.0|^11.0|^12.0",
        "illuminate/http": "^9.0|^10.0|^11.0|^12.0",
        "illuminate/database": "^9.0|^10.0|^11.0|^12.0"
    },
    "autoload": {
        "psr-4": {
            "Bog\\Payment\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Bog\\Payment\\BogPaymentServiceProvider"
            ]
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### 2.2 Create Tags

Create a git tag for versioning:

```bash
# Tag current version
git tag -a v1.0.0 -m "Version 1.0.0 - Initial release"

# Push tags to GitHub
git push origin v1.0.0
```

## Step 3: Publish to Packagist.org

### 3.1 Create Packagist Account

1. Go to [Packagist.org](https://packagist.org)
2. Click "Sign up" at the top right
3. Sign up with GitHub (recommended)

### 3.2 Submit Your Package

1. Log in to Packagist
2. Click "Submit" in the top menu
3. Enter your GitHub repository URL:
   ```
   https://github.com/YOUR_USERNAME/bog-payment
   ```
4. Click "Check"
5. Packagist will analyze your package
6. Click "Submit" to publish

### 3.3 Enable Auto-Update (Recommended)

1. Go to your package page on Packagist
2. Click "Update Check"
3. Click "Enable Auto-Update"
4. Connect your GitHub account for automatic updates

## Step 4: Verify Publication

### 4.1 Test Installation

Create a test Laravel project:

```bash
# Create new project
composer create-project laravel/laravel test-bog-payment

cd test-bog-payment

# Install your package
composer require yourusername/bog-payment
```

### 4.2 Verify Package Works

```php
// In tinker or a controller
use Bog\Payment\Services\BogAuthService;

$auth = new BogAuthService();
// Should work without errors
```

## Step 5: Updating Your Package

When you make updates:

```bash
# Make your changes
git add .
git commit -m "Description of changes"

# Push to GitHub
git push origin main

# Create new version tag
git tag -a v1.0.1 -m "Version 1.0.1 - Added new feature"
git push origin v1.0.1

# Update CHANGELOG.md
```

Packagist will auto-update if enabled.

## Step 6: Promote Your Package

### 6.1 Update README

Make sure your README is complete with:
- Clear description
- Installation instructions
- Usage examples
- License information

### 6.2 Add Badges

Add badges to your README:

```markdown
![Packagist Version](https://img.shields.io/packagist/v/yourusername/bog-payment)
![Packagist Downloads](https://img.shields.io/packagist/dt/yourusername/bog-payment)
![License](https://img.shields.io/packagist/l/yourusername/bog-payment)
```

### 6.3 Share Your Package

- Post on social media
- Share in Laravel communities
- Add to Laravel package lists
- Write a blog post

## Important Notes

### Package Name

Your package name should follow this format:
```
githubusername/package-name
```

Example: `johndoe/bog-payment`

### Version Numbers

Follow semantic versioning:
- `1.0.0` - Initial stable release
- `1.0.1` - Bug fixes
- `1.1.0` - New features (backward compatible)
- `2.0.0` - Breaking changes

### GitHub Repository Must Be Public

Free Packagist accounts require:
- Public GitHub repository
- No private repositories (pay for private)

### Required Files

Make sure you have:
- `composer.json` ✅
- `README.md` ✅
- `LICENSE` (recommended)
- `.gitignore` ✅

## Troubleshooting

### "Could not find package"

- Check repository URL is correct
- Ensure repository is public
- Wait a few minutes after pushing to GitHub

### "Package name already exists"

- Choose a different name
- Check if the package exists first

### Auto-update not working

- Verify webhook is set in GitHub
- Check Packagist logs
- Manually trigger update

## Next Steps

1. ✅ Create GitHub repository
2. ✅ Push code to GitHub
3. ✅ Submit to Packagist
4. ✅ Test installation
5. ✅ Share with community

Congratulations! Your package is now available for everyone to use! 🎉

## Links

- [Packagist.org](https://packagist.org)
- [Semantic Versioning](https://semver.org)
- [Laravel Packages](https://laravelpackage.com)
