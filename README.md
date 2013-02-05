# Google Issues to Github

Command line script written in PHP to migrate issues from a Google Code project to Github.

Early draft. Don't use.

### Get a Github OAuth token from the CLI

```
curl -u ':USERNAME' -d '{"scopes":["public_repo"],"note":"Google Issues to GH"}' https://api.github.com/authorizations
curl -H "Authorization: bearer :TOKEN" https://api.github.com/users/:USERNAME -I
```
Replace `:USERNAME` with your Github username and `:TOKEN` with the oauth token you get from first curl  
After that, the token will be listed in your [Applications](https://github.com/settings/applications) and you can revoke it from there
