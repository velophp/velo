<p align="center"><a href="https://velobase.io" target="_blank"><img src="./public/assets/velo.svg" height="150" alt="Velo Logo"></a></p>

# Velo Documentation

Welcome to the official documentation for Velo, a Laravel-based backend-as-a-service platform designed for flexibility, speed, and ease of use.

---

## Getting Started

To understand how Velo works and how to integrate it into your projects, explore the following sections:

- **[Core Concepts](core-concepts.md)**: Dive into Velo's architecture, including EAV (Entity-Attribute-Value) schema and dynamic collections.
- **[Authentication](authentication.md)**: Learn about multi-collection user providers, stateful authentication, and session management.
- **[Realtime](realtime.md)**: Explore how to receive instant data updates via WebSockets with granular filtering support.
- **[Hooks System](hooks.md)**: Learn how to extend Velo's core logic with custom triggers and event listeners.

---

## Key Features

- **Dynamic Collections**: Manage your data schema at runtime without the need for manual migrations.
- **Collection Types**: Use **Base** collections for standard data and **Auth** collections for user management.
- **Realtime Updates**: Native support for **Laravel Reverb** and **Pusher** for instant data synchronization.
- **Extensible Architecture**: Use the internal **Hooks System** to intercept and modify system behavior.

---

## Tech Stack

Velo is built on a modern, robust foundation:
- **Framework**: [Laravel 12](https://laravel.com)
- **Frontend**: [Livewire 4](https://livewire.laravel.com) & [TailwindCSS](https://tailwindcss.com)
- **UI Components**: [MaryUI](https://mary-ui.com)

---

## Contributing

Thank you for considering contributing to the Velo project!

## Improvement & Security Vulnerabilities

If you discover a security vulnerability within Velo, please open a new issue or send an email to the maintainers.

---

## License

Velo is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
