# Using Shipper for Your Repository

This guide explains how to use the Shipper deployment tool in another repository to implement production, staging, and preview deployments from GitHub Actions.

## Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Step-by-Step Setup](#step-by-step-setup)
- [GitHub Actions Configuration](#github-actions-configuration)
- [Access Setup for Private Repositories](#access-setup-for-private-repositories)
- [Configuration Reference](#configuration-reference)
- [Troubleshooting](#troubleshooting)

## Overview

Shipper is a CLI tool that provides declarative, Infrastructure-as-Code style deployments using a simple YAML configuration file. It currently supports deploying to Ploi-managed servers and includes built-in support for:

- **Production deployments** from `main` branch
- **Staging deployments** from `develop` branch  
- **Preview deployments** from pull requests (with automatic cleanup)
- **Database provisioning** and lifecycle management
- **Multi-project support** in monorepos

## Prerequisites

Before using Shipper in your repository, you need:

### 1. Server Setup
- A server managed by [Ploi.io](https://ploi.io)
- Your Ploi Server ID (found in Ploi dashboard)

### 2. Ploi API Access
- A Ploi API key with permissions to:
  - Create and manage sites
  - Deploy code
  - Manage databases (if using database features)

### 3. Repository Requirements
- PHP 8.3+ with extensions: `mbstring`, `xml`, `ctype`, `json`, `yaml`
- Composer installed
- Git repository hosted on GitHub, GitLab, or Bitbucket

### 4. GitHub Secrets
The following secrets must be configured in your repository (Settings → Secrets and variables → Actions):
- `PLOI_API_KEY` - Your Ploi API key

## Quick Start

**Prompt for implementation:**

```
Please add Shipper deployment to my repository with production/staging/preview environments:

1. Install Shipper as a dependency or subtree
2. Create a shipper.yml configuration for my project:
   - Project name: [your-project-name]
   - Repository: [org/repo-name]
   - Ploi server ID: [your-server-id]
   - Production domain: [your-production-domain]
   - Staging domain: [your-staging-domain]
   - Preview domain pattern: [your-preview-pattern]
   - Web directory: [/public or your web root]
   - Database configuration: [if needed]
3. Add GitHub Actions workflows for:
   - deploy-production.yml (triggers on push to main)
   - deploy-staging.yml (triggers on push to develop)
   - deploy-preview.yml (triggers on pull requests)
   - cleanup-preview.yml (triggers when PRs close)
4. Configure PLOI_API_KEY secret in GitHub repository settings
```

## Step-by-Step Setup

### Step 1: Add Shipper to Your Repository

You have two options:

#### Option A: Install as Subtree (Recommended)

This embeds Shipper directly in your repository:

```bash
# Add the shipper repository as a remote
git remote add shipper-upstream https://github.com/ulties/deployer-wip.git

# Add shipper as a subtree in a 'shipper' directory
git subtree add --prefix=shipper shipper-upstream main --squash

# To update later:
git subtree pull --prefix=shipper shipper-upstream main --squash
```

#### Option B: Use as Separate Tool

Clone and reference externally, or use as a GitHub Action:

```bash
# In your workflow, clone the shipper repo
- name: Clone Shipper
  run: git clone https://github.com/ulties/deployer-wip.git shipper-tool

- name: Install Shipper dependencies
  run: cd shipper-tool && composer install --no-dev
```

### Step 2: Create shipper.yml Configuration

Create a `shipper.yml` file in your repository root:

```yaml
# shipper.yml - Deployment configuration

providers:
  ploi:
    api_key: "${PLOI_API_KEY}"
    api_url: "https://ploi.io/api"
    server_id: "YOUR_SERVER_ID"  # Replace with your Ploi server ID
    deployment_timeout: 60  # Seconds to wait for deployment

projects:
  # Main application project
  app:
    provider: ploi
    path: .  # Root of repository (or ./subdirectory for monorepos)
    
    # Repository configuration
    repository:
      provider: github  # or gitlab, bitbucket
      name: your-org/your-repo  # Replace with your repository
    
    # Site configuration
    web_directory: /public  # Laravel default, adjust for your framework
    project_root: /  # Usually / unless using subdirectory
    
    # Database configuration (optional)
    databases:
      main:
        name: "yourapp_${PROFILE}_${GITHUB_PR_NUMBER}"
        user: "yourapp_${PROFILE}_${GITHUB_PR_NUMBER}"
        type: mysql  # or postgresql, mariadb
    
    # Deployment profiles
    profiles:
      production:
        branch: main
        domain: your-app.yourdomain.com
      
      staging:
        branch: develop
        domain: your-app-staging.yourdomain.com
      
      preview:
        branch: "${GITHUB_HEAD_REF}"
        domain: "your-app-preview-${GITHUB_PR_NUMBER}.yourdomain.com"
```

### Step 3: Configure GitHub Repository Secrets

1. Go to your repository on GitHub
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add the following secret:
   - Name: `PLOI_API_KEY`
   - Value: Your Ploi API key (from Ploi.io dashboard)

### Step 4: Add GitHub Actions Workflows

Create the following workflow files in `.github/workflows/`:

#### deploy-production.yml

```yaml
name: Deploy to Production

on:
  push:
    branches:
      - main

permissions:
  contents: read

jobs:
  deploy:
    runs-on: ubuntu-latest
    name: Deploy to Production
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, yaml
          coverage: none
      
      - name: Cache Composer packages
        uses: actions/cache@v3
        with:
          path: shipper/vendor
          key: ${{ runner.os }}-shipper-${{ hashFiles('shipper/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-shipper-
      
      - name: Install Shipper dependencies
        run: cd shipper && composer install --prefer-dist --no-progress --no-dev
      
      - name: Validate configuration
        run: ./shipper/shipper validate
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
      
      - name: Plan deployment
        run: ./shipper/shipper plan app --profile=production
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
      
      - name: Deploy to production
        run: ./shipper/shipper apply app --profile=production --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

#### deploy-staging.yml

```yaml
name: Deploy to Staging

on:
  push:
    branches:
      - develop

permissions:
  contents: read

jobs:
  deploy:
    runs-on: ubuntu-latest
    name: Deploy to Staging
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, yaml
          coverage: none
      
      - name: Cache Composer packages
        uses: actions/cache@v3
        with:
          path: shipper/vendor
          key: ${{ runner.os }}-shipper-${{ hashFiles('shipper/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-shipper-
      
      - name: Install Shipper dependencies
        run: cd shipper && composer install --prefer-dist --no-progress --no-dev
      
      - name: Validate configuration
        run: ./shipper/shipper validate
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
      
      - name: Plan deployment
        run: ./shipper/shipper plan app --profile=staging
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
      
      - name: Deploy to staging
        run: ./shipper/shipper apply app --profile=staging --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
```

#### deploy-preview.yml

```yaml
name: Deploy Preview

on:
  pull_request:
    branches:
      - main
      - develop

permissions:
  contents: read
  pull-requests: write

jobs:
  deploy:
    runs-on: ubuntu-latest
    name: Deploy Preview
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, yaml
          coverage: none
      
      - name: Cache Composer packages
        uses: actions/cache@v3
        with:
          path: shipper/vendor
          key: ${{ runner.os }}-shipper-${{ hashFiles('shipper/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-shipper-
      
      - name: Install Shipper dependencies
        run: cd shipper && composer install --prefer-dist --no-progress --no-dev
      
      - name: Validate configuration
        run: ./shipper/shipper validate
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Plan deployment
        run: ./shipper/shipper plan app --profile=preview
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Deploy preview
        run: ./shipper/shipper apply app --profile=preview --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Comment PR
        uses: actions/github-script@v7
        with:
          script: |
            const domain = 'your-app-preview-${{ github.event.pull_request.number }}.yourdomain.com';
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `🚀 Preview deployed!\n\n**URL:** https://${domain}\n**Branch:** \`${{ github.head_ref }}\`\n**Profile:** preview`
            })
```

#### cleanup-preview.yml

```yaml
name: Cleanup Preview

on:
  pull_request:
    types: [closed]
    branches:
      - main
      - develop

permissions:
  contents: read
  pull-requests: write

jobs:
  cleanup:
    runs-on: ubuntu-latest
    name: Cleanup Preview
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, yaml
          coverage: none
      
      - name: Cache Composer packages
        uses: actions/cache@v3
        with:
          path: shipper/vendor
          key: ${{ runner.os }}-shipper-${{ hashFiles('shipper/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-shipper-
      
      - name: Install Shipper dependencies
        run: cd shipper && composer install --prefer-dist --no-progress --no-dev
      
      - name: Destroy preview site
        run: ./shipper/shipper destroy app --profile=preview --force
        env:
          PLOI_API_KEY: ${{ secrets.PLOI_API_KEY }}
          GITHUB_HEAD_REF: ${{ github.head_ref }}
          GITHUB_PR_NUMBER: ${{ github.event.pull_request.number }}
      
      - name: Comment PR
        uses: actions/github-script@v7
        with:
          script: |
            const domain = 'your-app-preview-${{ github.event.pull_request.number }}.yourdomain.com';
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `🧹 Preview site cleaned up!\n\n**Domain:** ${domain}\n**Profile:** preview`
            })
```

## Access Setup for Private Repositories

When both the Shipper repository and your target repository are **private** and in the **same GitHub organization**, additional access configuration is required.

### Why Access Setup is Needed

GitHub Actions workflows run in an isolated environment. When the workflow needs to access another private repository (like pulling Shipper as a subtree or cloning it), it needs proper authentication.

### Solution 1: Using GITHUB_TOKEN (Recommended for Same Org)

For repositories in the same organization, use the built-in `GITHUB_TOKEN` with appropriate permissions:

1. **Enable Organization Access** (Organization Setting):
   - Go to your **Organization Settings** → **Actions** → **General**
   - Under "Workflow permissions", ensure the default `GITHUB_TOKEN` has "Read repository contents and packages" permission
   - Enable "Allow GitHub Actions to access private repositories" if available

2. **Update Workflow Permissions**:

   Add these permissions to your workflow files if using subtree or cloning:
   
   ```yaml
   permissions:
     contents: read
     # Add if the shipper repo is private in same org:
     packages: read
   ```

### Solution 2: Using a Personal Access Token (PAT)

If the automatic token doesn't work or repositories are in different organizations:

1. **Create a Personal Access Token**:
   - Go to **GitHub Settings** → **Developer settings** → **Personal access tokens** → **Tokens (classic)**
   - Click "Generate new token (classic)"
   - Select scopes:
     - `repo` (Full control of private repositories)
     - `read:org` (if needed for organization access)
   - Generate and copy the token

2. **Add PAT as Repository Secret**:
   - In your target repository: **Settings** → **Secrets and variables** → **Actions**
   - Add secret: `SHIPPER_ACCESS_TOKEN` with the PAT value

3. **Update Checkout Step in Workflows**:
   
   ```yaml
   - name: Checkout code
     uses: actions/checkout@v4
     with:
       token: ${{ secrets.SHIPPER_ACCESS_TOKEN }}
       submodules: true  # If using subtrees
   ```

### Solution 3: Using Deploy Keys

For tighter security, use deploy keys:

1. **Generate SSH Key Pair**:
   ```bash
   ssh-keygen -t ed25519 -C "shipper-deployment" -f shipper_deploy_key
   ```

2. **Add Public Key to Shipper Repository**:
   - In the Shipper repo: **Settings** → **Deploy keys** → **Add deploy key**
   - Paste the public key content
   - Title it "Access from [your-repo]"
   - Check "Allow write access" if needed

3. **Add Private Key to Your Repository**:
   - In your repo: **Settings** → **Secrets and variables** → **Actions**
   - Add secret: `SHIPPER_DEPLOY_KEY` with the private key content

4. **Update Workflow to Use SSH Key**:
   ```yaml
   - name: Setup SSH for private repos
     uses: webfactory/ssh-agent@v0.8.0
     with:
       ssh-private-key: ${{ secrets.SHIPPER_DEPLOY_KEY }}
   
   - name: Clone Shipper
     run: |
       git clone git@github.com:ulties/deployer-wip.git shipper
   ```

### Verification

Test your access setup:

1. Create a test pull request
2. Check if the GitHub Actions workflow runs successfully
3. Verify that Shipper is properly accessed/installed
4. Check workflow logs for any authentication errors

## Configuration Reference

### Environment Variables in Configuration

Shipper supports environment variable interpolation in `shipper.yml`:

| Variable | Description | Example |
|----------|-------------|---------|
| `${PLOI_API_KEY}` | Your Ploi API key | Set in GitHub Secrets |
| `${GITHUB_HEAD_REF}` | PR branch name | `feature/new-feature` |
| `${GITHUB_PR_NUMBER}` | PR number | `42` |
| `${PROJECT_NAME}` | Project name from config | `app` |
| `${PROFILE}` | Deployment profile | `production`, `staging`, `preview` |

### Database Configuration

Database names support variable substitution for dynamic naming:

```yaml
databases:
  main:
    # Pattern: appname_profile_prnumber
    name: "myapp_${PROFILE}_${GITHUB_PR_NUMBER}"
    user: "myapp_${PROFILE}_${GITHUB_PR_NUMBER}"
    type: mysql  # mysql, postgresql, mariadb
```

For preview environments (PR #42):
- Database name: `myapp_preview_42`
- Username: `myapp_preview_42`

For production/staging (no PR number):
- Production: `myapp_production`
- Staging: `myapp_staging`

### Multi-Project Configuration (Monorepos)

For repositories with multiple projects:

```yaml
projects:
  api:
    provider: ploi
    path: ./packages/api
    repository:
      provider: github
      name: your-org/monorepo
    profiles:
      production:
        branch: main
        domain: api.yourdomain.com
  
  frontend:
    provider: ploi
    path: ./packages/frontend
    repository:
      provider: github
      name: your-org/monorepo
    profiles:
      production:
        branch: main
        domain: frontend.yourdomain.com
```

Update GitHub Actions to deploy specific projects:

```yaml
strategy:
  matrix:
    project: [api, frontend]

steps:
  - name: Deploy ${{ matrix.project }}
    run: ./shipper/shipper apply ${{ matrix.project }} --profile=production --force
```

## Troubleshooting

### Issue: "Configuration file not found"

**Solution**: Ensure `shipper.yml` exists in your repository root and is committed to git.

### Issue: "Invalid Ploi API key"

**Solution**: 
1. Verify your API key in Ploi.io dashboard
2. Check that the `PLOI_API_KEY` secret is correctly set in GitHub
3. Ensure the secret name matches what's used in workflows

### Issue: "Server not found" or "Invalid server_id"

**Solution**:
1. Log in to Ploi.io
2. Go to your server
3. Copy the numeric Server ID from the URL or dashboard
4. Update `server_id` in `shipper.yml`

### Issue: "Repository access denied"

**Solution**: 
1. Ensure your Ploi server has SSH access to your repository
2. Add the Ploi server's SSH key to your repository's deploy keys
3. For private repos, see [Access Setup for Private Repositories](#access-setup-for-private-repositories)

### Issue: "Domain already exists"

**Solution**: 
1. Check if the domain is already configured in Ploi
2. Either delete the existing site or change your domain in `shipper.yml`
3. Ensure preview domains include unique identifiers (like PR numbers)

### Issue: "Database creation failed"

**Solution**:
1. Ensure your Ploi API key has database management permissions
2. Check database name follows MySQL/PostgreSQL naming rules (alphanumeric and underscore only)
3. Verify the database type matches your server configuration

### Issue: "Workflow runs but deployment doesn't happen"

**Solution**:
1. Check workflow logs in GitHub Actions
2. Verify all required environment variables are set
3. Run `./shipper validate` locally to check configuration
4. Ensure the branch being deployed matches the profile configuration

### Issue: "Preview deployments not cleaning up"

**Solution**:
1. Verify `cleanup-preview.yml` workflow exists and is enabled
2. Check that it has correct permissions (`contents: read`, `pull-requests: write`)
3. Ensure the workflow triggers on PR close: `types: [closed]`

### Getting Help

If you encounter issues:

1. **Check Workflow Logs**: GitHub Actions → Select failed workflow → View logs
2. **Validate Locally**: Run `./shipper validate` to check your configuration
3. **Review Ploi Logs**: Check deployment logs in Ploi.io dashboard
4. **Enable Debug Logging**: Add `ACTIONS_STEP_DEBUG: true` to workflow environment variables

For more information, see the main [README.md](README.md).
