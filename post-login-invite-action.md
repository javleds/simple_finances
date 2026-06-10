# Acción post-login para invitaciones pendientes

## Contexto

Cuando una persona recibe una invitación para una cuenta compartida, el correo debe enviarla primero al flujo de autenticación correcto:

- Usuario existente: `SPA_URL/login`
- Usuario no registrado: `SPA_URL/register`

En ambos casos el link debe incluir una acción post-auth para que la SPA redirija a la pantalla de invitaciones pendientes después de autenticar al usuario.

## Contrato del link de invitación

La API genera links hacia la SPA con estos parámetros:

```text
SPA_URL/login?email={email}&post_auth_action=account-invites
SPA_URL/register?email={email}&post_auth_action=account-invites
```

La SPA debe preservar `post_auth_action` al enviar el request de login o registro a la API.

## Contrato de login y registro

Los endpoints aceptan el campo opcional:

```json
{
  "post_auth_action": "account-invites"
}
```

Si el usuario autenticado tiene invitaciones pendientes para su email, la API responde:

```json
{
  "meta": {
    "post_auth_redirect": {
      "action": "account-invites",
      "url": "SPA_URL/admin/invitations"
    }
  }
}
```

Si no hay invitaciones pendientes o la acción no se envía, la API no incluye `meta.post_auth_redirect`.

## Responsabilidad de la SPA

Después de login o registro exitoso:

1. Leer `meta.post_auth_redirect`.
2. Validar que `action` sea `account-invites`.
3. Navegar a `url`, que debe resolver a `SPA_URL/admin/invitations`.

La SPA no debe construir redirects arbitrarios desde parámetros libres del usuario. La acción permitida es `account-invites`.
