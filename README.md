<p align="center"><a href="https://velobase.io" target="_blank"><img src="./public/assets/velo.svg" height="200" alt="Velo Logo"></a></p>

# Velo

## About Velo

Velo is a Laravel-based backend utilizing MySQL as the primary database and EAV (Entity-Attribute-Value) schema for dynamic collections.

The goal of this project is to make a Backend-as-a-service platform that is cheap to deploy whilst maintaining key features of a Backend-as-a-service platform.
This project is heavily inspired by [PocketBase](https://pocketbase.io) and its open-source nature.

### Key Features
- **Dynamic Collections**: Create and manage data schemas on the fly without writing migrations.
- **Collection Types**: Support for different collection types (Base, Auth, View (coming soon)).
- **Realtime**: Built-in WebSocket support via **Pusher** and **Laravel Reverb**.
- **Admin Panel**: Modern, reactive admin panel built using Livewire 4.

### Tools & Technologies Used
This project is proudly built with:
- [Laravel 12](https://laravel.com)
- [Livewire 4](https://livewire.laravel.com)
- [TailwindCSS](https://tailwindcss.com)
- [MaryUI](https://mary-ui.com)

## Documentation

Full documentation is available in the `docs/` directory:

- [Core Concepts & Architecture](docs/core-concepts.md)
- [API Reference](docs/api-reference.md)
- [Hooks Extension System](docs/hooks.md)

## Contributing

Thank you for considering contributing to the Velo project!

## Improvement & Security Vulnerabilities

If you discover a security vulnerability within Velo, please open a new issue or send an email to the maintainers.

## License

Velo is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
