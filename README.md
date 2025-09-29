# Contacts API

A RESTful API for managing users and their contacts, built with PHP, JWT authentication, and MySQL.

A working demo can be found at: _https://inesculent.dev/project/public/login.html_

## Features

- **User Management**: Create, read, update, and delete users
- **Contact Management**: Create, read, and list contacts for authenticated users
- **JWT Authentication**: Secure API access with JSON Web Tokens
- **Cookie & Bearer Token Support**: Flexible authentication methods
- **Input Validation**: Comprehensive validation with detailed error messages
- **Development Tools**: Debug endpoints and configurable security settings

## Tech Stack

- **Backend**: PHP 8.1+ with strict types
- **Database**: MySQL with PDO
- **Authentication**: JWT with Firebase JWT library
- **Architecture**: Repository pattern with clean separation of concerns

## Quick Start

### Prerequisites
- PHP 8.1+
- MySQL 5.7+
- Composer

### Installation
1. Clone the repository
2. Install dependencies: `composer install`
3. Configure database in `src/config.php`
4. Import database schema from `sql/schema/`
5. Start your web server pointing to `public/index.php`

### Configuration
Edit `src/config.php`:
```php
'db' => [
    'host' => '127.0.0.1',
    'name' => 'contacts_app',
    'user' => 'your_user',
    'pass' => 'your_password',
],
'auth' => [
    'jwt_secret' => 'your-secret-key',
    'access_ttl' => 3600, // 1 hour
],
'dev' => [
    'show_cookies' => true,  // Set to false in production
    'secure_cookies' => false, // Set to true in production with HTTPS
],
```

## API Endpoints

### Health Check
```
GET /health
```
Returns service status and routing information.

### Authentication

#### Create User (Public)
```
POST /users
Content-Type: application/json

{
  "name": "Rob L",
  "email": "tuff@example.com",
  "password": "COP4331"
}
```
**Responses:**
- `201 Created` → User created with ID
- `422 Unprocessable Entity` → Validation errors
- `409 Conflict` → Email already exists

#### Login (Public)
```
POST /auth/login
Content-Type: application/json

{
  "email": "tuff@example.com",
  "password": "COP4331"
}
```
**Responses:**
- `200 OK` → Login successful, sets auth cookie and returns token
- `422 Unprocessable Entity` → Invalid credentials


#### Logout (Protected)
```
POST /auth/logout
Authorization: Bearer {jwt_token}
Content-Type: application/json

{}
```
**Responses:**
- `200 OK` → Successfully logged out, token revoked and cookie cleared
- `400 Bad Request` → Failed to logout (invalid token)
- `401 Unauthorized` → Authentication required

**Note:** This endpoint revokes the current access token by adding it to a blacklist, making it immediately invalid for future requests. If using cookie authentication, the auth cookie is automatically cleared.

### User Management (Protected)

All user endpoints require authentication (cookie or Bearer token).

#### Get User Profile
```
GET /users/{uid}
Authorization: Bearer {jwt_token}
```

#### Update User
```
PATCH /users/{uid}
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "new@example.com",
  "password": "newpassword123"
}
```

#### Delete User
```
DELETE /users/{uid}
Authorization: Bearer {jwt_token}
```

### Contact Management (Protected)

#### Create Contact
```
POST /users/{uid}/contacts
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "name": "Jane Smith",
  "phone": "555-123-4567",
  "email": "jane@example.com"
}
```
**Note:** Either `phone` or `email` (or both) must be provided.

#### List User's Contacts
```
GET /users/{uid}/contacts
Authorization: Bearer {jwt_token}
```

#### Get Specific Contact
```
GET /contacts/{cid}
Authorization: Bearer {jwt_token}
```

#### Update Contact
```
PATCH /contacts/{cid}
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "name": "Updated Name",
  "phone": "111-222-3333",
  "email": "updated@example.com"
}
```
**Note:** You can update any combination of `name`, `phone`, and `email`. The contact must have at least one of `phone` or `email` after the update.

#### Delete Contact
```
DELETE /contacts/{cid}
Authorization: Bearer {jwt_token}
```

### Development Endpoints

#### Check Authentication Status
```
GET /dev/auth
```
Shows current authentication status, cookie information, and configuration.

## Authentication

The API supports two authentication methods:

### 1. Cookie-based (Automatic)
After login, an `auth` cookie is set automatically. Subsequent requests include this cookie.

### 2. Bearer Token (Manual)
Include the JWT token in the Authorization header:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

## Error Handling

### HTTP Status Codes
- `200 OK` → Success
- `201 Created` → Resource created
- `401 Unauthorized` → Authentication required
- `403 Forbidden` → Access denied (wrong user)
- `404 Not Found` → Resource not found
- `405 Method Not Allowed` → Invalid HTTP method
- `409 Conflict` → Duplicate resource (email)
- `422 Unprocessable Entity` → Validation errors
- `500 Internal Server Error` → Server error

### Error Response Format
```json
{
  "status": "error",
  "code": "INVALID_EMAIL",
  "message": "Email format invalid or too long",
  "meta": {
    "field": "email"
  }
}
```

### Common Error Codes
- `INVALID_INPUT` → Missing required fields
- `INVALID_NAME` → Name validation failed
- `INVALID_EMAIL` → Email validation failed
- `INVALID_PHONE` → Phone validation failed
- `INVALID_PASSWORD` → Password validation failed
- `DUPLICATE_EMAIL` → Email already registered
- `NOT_FOUND` → Resource not found
- `UNAUTHENTICATED` → Login required
- `FORBIDDEN` → Access denied
- `DB_ERROR` → Database error
- `NOT_IMPLEMENTED` → Feature not yet available

## Validation Rules

### User Validation
- **Name**: Required, max 255 characters
- **Email**: Required, valid email format, max 255 characters, unique
- **Password**: Required, min 8 characters, max 255 characters

### Contact Validation

#### Creating Contacts
- **Name**: Required, max 255 characters
- **Phone**: Optional, max 32 characters (required if no email)
- **Email**: Optional, max 255 characters (required if no phone)

#### Updating Contacts
- **Name**: Optional, max 255 characters (if provided)
- **Phone**: Optional, max 32 characters (if provided)
- **Email**: Optional, max 255 characters (if provided)
- **Constraint**: After update, contact must have at least one of phone or email

## Security Features

- **Strict Type Declarations**: All PHP files use `declare(strict_types=1)`
- **Prepared Statements**: All database queries use prepared statements
- **Password Hashing**: Bcrypt password hashing
- **JWT Security**: Configurable issuer, audience, and expiration
- **Input Validation**: Comprehensive input validation and sanitization
- **Error Logging**: Server-side error logging without exposing internals

## Development & Testing

### Using Postman
1. Create a user with `POST /users`
2. Login with `POST /auth/login`
3. Use the returned `auth_token` as a Bearer token
4. Or rely on automatic cookie handling
5. Test contact endpoints with `POST /users/{uid}/contacts`

### Environment Variables
Set these for production:
- `JWT_SECRET` → Your secure JWT secret
- `DEV_MODE=false` → Disable development features
- `SECURE_COOKIES=true` → Enable secure cookies for HTTPS

## Project Structure

```
src/
├── Infrastructure/
│   ├── AuthMiddleware.php    # JWT authentication
│   ├── AuthService.php       # JWT token generation
│   ├── DBManager.php         # Database abstraction
│   ├── LoadSql.php           # SQL file loader
│   └── Result.php            # Response wrapper
├── Repos/
│   ├── UserRepo.php          # User data operations
│   └── ContactRepo.php       # Contact data operations
└── config.php                # Application configuration

sql/
├── schema/                   # Database schema
└── queries/                  # SQL query files

public/
└── index.php                 # Application entry point
