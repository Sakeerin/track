Place TLS certificate files in this directory for production deployment.

Required filenames:
- `fullchain.pem`
- `privkey.pem`

For local validation, you can generate a self-signed certificate:

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout privkey.pem \
  -out fullchain.pem \
  -subj "/CN=localhost"
```
