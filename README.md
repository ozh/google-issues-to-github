# Google Issues to Github

Command line script written in PHP to migrate issues from a Google Code project to Github.

**In a nutshell:**  
1. the script reads issues from your Google Project  
2. For every issue, it creates an issue on Github  
3. If the Issue is closed on Google, it closes it on Github  

**Showcase**: [YOURLS issues](https://github.com/YOURLS/YOURLS/issues?direction=asc&page=1&sort=created&state=closed), migrated from [Google Code](http://code.google.com/p/yourls/issues/list).

## Features

This script will duplicate issues from a given Google Code project into a Github project.

* It will mirror all issues: `Issue 43` on Google will be `Issue 43` on Github. For this reason, you'll want to use this script on a Github project with **0 issue**. If the number matching cannot be preserved for whatever reason, the script will halt.

* If an issue has been **deleted on Google**, it will create a dummy issue on Github to preserve issue number matching (*ie* if you have issues 1, 2, 3 and 5 on Google, you'll end up with issues 1, 2, 3, **4** and 5 on Github, where Issue 4 will just say "Deleted issue" or something similar (this is configurable)

* The script will migrate only **issue descriptions**, not comments or attachments. You'll end up with a *shadow issue* pointing at the original Google Project issue. Issue states (open or closed) will be preserved on Github.

* The script is very **conservative**: whenever a Github API request fails (timeout, network glitch, server load, alien abduction...) it will pause then retry a configurable number of times.

* The script is very **configurable**. For instance, you can easily customize the text of mirrored issues, to explain the whys and whats of the migration.

## Manual

### 1. Get a Github OAuth token

Here's a very easy way, from the command line, using `curl`:

```
curl -u ':USERNAME' -d '{"scopes":["public_repo"],"note":"Google Issues to GH"}' https://api.github.com/authorizations

curl -H "Authorization: bearer :TOKEN" https://api.github.com/users/:USERNAME -I
```

Replace `:USERNAME` with your Github username and `:TOKEN` with the oauth token you get from first curl  

After that, the token will be listed in your [Applications](https://github.com/settings/applications) and you can revoke it from there

### 2. Configure the script

There are several required and optional settings at the beginning of the script. Everything is commented and self explanatory.

### 3. Run the script

This script will run only from the command line, as you should not suffer from PHP `max_execution_time` directive.

The process will be fairly long: count roughly 1 second per issue. 1000 issues = 15 minutes, give or take.

## FAQ

* **Pardon my newbism, but how do you run PHP from the command line?**  
In your shell, type `which php` to get the PHP path (eg `/usr/bin/php`), then simply `/usr/bin/php myscript.php`

* **It doesn't work!**  
It worked fine for me.

* **Sounds neat but I don't like PHP, any advice?**  
Sure. There are a [lot of other alternatives](https://www.google.com/search?q=google+code+to+github), in Python, Ruby or Perl.

* **Can you update the script with that feature?**  
Probably not, I made this script for my own use and it matched my needs. Who knows, go for it and ask! Just be prepared to be ignored :)

## Tips

* **Read the script**  
There's nothing complicated in there, it will help you troubleshoot any issue that you might have. A lot of commented out `var_dump()` will give you pointers about the important parts.

* **Test run in an empty project.**  
Create a dummy repo on Github and migrate your issues there, and see if it works for you. On Github, you cannot delete issues (you can on Google) so be sure you've got everything right first.

* **Test run with screen output first**  
Before getting the script to create issues using the Github API, make it display results, especially if you've edited the script to cope with the 1000 issues barrier (see below). Check `function post_to_gh()` and uncomment the first block.

* **Google limit issue feeds to 1000 items**  
If your project has more, you'll need to run the script more than once. And before you run it for the second time or more, you'll need to slightly edit it:  
  * Check the last migrated issue number, add 1 and edit `ISSUE_START_FROM` with that value
  * Check the 'published' time of the last issue migrated, add one second to it and edit `PUBLISHED_MIN` with that value. It will be something like `'2012-03-12T13:37:01'`
  * Run the script again (a test run first to make sure you've edited stuff OK)

## Credits

Inspiration for this script originally came from @trentm's [googlecode2github](https://github.com/trentm/googlecode2github) script, and especially the `shadowissues.py` part of it. I didn't speak enough Python to customize his script for my needs so, well, I made one :)

## License

Script distributed in the hope that it will be useful and/or fun to use. There is absolutely no guarantee of any kind about anything.

Do whatever the hell you want with it.















