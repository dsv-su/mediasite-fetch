## auth.json

This file is required for this tool to work. It contains credentials for Mediasite/Daisy API access.

```json
{
    "play2":
        {
            "username": "username",
            "password": "password",
            "url": "apiurl",
            "sfapikey": "apikey"
        },
    "daisy":
        {
            ...
        }
}
```
## Usage
```bash
php mediasite.php -u(--username) %username% -o(--op) 1
```
This will dump the report file to the output buffer, so it is then could be read by any other tool.
