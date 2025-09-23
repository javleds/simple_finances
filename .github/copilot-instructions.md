# Copilot Instructions

## Code Generation Principles

### Language Standards
- Use PHP 8 syntax for all new code generated
- Leverage modern PHP features like constructor property promotion, match expressions, enums, and union types
- Follow strict typing where applicable

### Framework Guidelines
- Follow Laravel default recommendations and conventions
- Adhere to Laravel's directory structure and naming conventions
- Use Laravel's built-in features and helpers when available
- Follow PSR-12 coding standards

### UI Development
- Consider FilamentFirst approach for complete UI implementations
- Prioritize Filament components and patterns for admin interfaces
- Use Filament's form builders, tables, and resource patterns

### Testing and Documentation
- Do not generate automated tests unless explicitly requested
- Do not generate documentation unless explicitly requested
- Focus on code implementation over auxiliary files

### Code Quality
- Do not add comments into the code unless explicitly requested
- Follow self-documenting code approach using descriptive variable, function, and class names
- Choose meaningful names that express intent and purpose
- Avoid redundant or obvious comments

### User Interface Guidelines
- Always provide user feedback messages in Spanish
- Do not use translation files for UI feedback - write messages directly in Spanish
- Ensure error messages, success notifications, and validation messages are in Spanish

### Architecture Patterns
- Service first approach - implement business logic in dedicated service classes
- Follow clean code principles
- Apply SOLID principles:
  - **Single Responsibility Principle**: Each class should have one reason to change
  - **Open/Closed Principle**: Classes should be open for extension, closed for modification
  - **Liskov Substitution Principle**: Objects should be replaceable with instances of their subtypes
  - **Interface Segregation Principle**: Depend on abstractions, not concretions
  - **Dependency Inversion Principle**: High-level modules should not depend on low-level modules

### Code Organization
- Separate concerns appropriately across layers (Controllers, Services, Models, DTOs)
- Use dependency injection for service dependencies
- Implement contracts/interfaces for service abstractions
- Keep controllers thin and delegate business logic to services
- Use DTOs for data transfer between layers when appropriate

### Laravel Specific Patterns
- Use Eloquent relationships and query builders effectively
- Implement proper validation using Form Requests
- Use Laravel's event/listener pattern for decoupled operations
- Leverage Laravel's built-in features like queues, caching, and middleware
- Use proper error handling and exception management
