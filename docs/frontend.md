# Frontend Structure

This document outlines the frontend architecture and conventions established for this project.

## Overview

The frontend is implemented using a server-side rendering
approach. [Twig](https://twig.symfony.com/) is utilized for templating, while client-side
interactivity is achieved through vanilla JavaScript, without reliance on transpilers or build
systems such as Webpack Encore.

## Core Technologies

* **[Twig](https://twig.symfony.com/)**: The templating engine used to generate the HTML structure.
  It is integrated via the Twig Bundle.
* **[Symfony Asset Component](https://symfony.com/doc/7.4/components/asset.html)**: Used for
  managing URL generation for static assets (CSS, JavaScript, images). It is integrated via
  `symfony/asset`.
* **Vanilla JavaScript**: Employed for dynamic client-side functionality.

## File Structure

Frontend-related files are organized into specific directories to ensure a clear separation of
concerns.

### Controllers

Web controllers responsible for rendering Twig templates are situated within the `UI/Web` layer of
each module.

* **Location**: `src/<Module>/UI/Web/<Contoller>.php`
* **Example**: `src/IAM/UI/Web/Login/LoginController.php`

These controllers handle HTTP requests, process data, and render the corresponding Twig templates.

### Templates

Twig templates are stored in the `templates/` directory at the project root. They are categorized
into the following subdirectories:

* `templates/layout/`: Contains base layout files (e.g., `base.html.twig`) defining the primary HTML
  structure.
* `templates/page/`: Contains templates for specific pages (e.g., `homepage.html.twig`,
  `login.html.twig`). These templates typically extend a base layout.
* `templates/component/`: Contains reusable template partials (e.g., `_tweet.html.twig`,
  `_login_form.html.twig`) intended for inclusion in other templates.

### Public Assets

Static assets served directly to the client are located in the `public/` directory.

* **JavaScript**: Custom JavaScript files are placed in `public/js/`.
    * **Location**: `public/js/`
    * **Example**: `public/js/login.js`
* **Images & Other Assets**: Additional assets, such as favicons, are also located in `public/`.

## Client-Side Scripting

Vanilla JavaScript is used for all client-side logic. Scripts are organized by feature or page
context. For example, logic specific to the login page is found in `public/js/login.js`.

A shared `api.js` file is utilized to handle communication with backend REST API endpoints,
providing a centralized mechanism for managing AJAX requests.
