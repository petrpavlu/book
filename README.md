# Tiny bookmark manager

This web application is a single-script bookmark manager. It was created to
provide a simple storage for links to web articles that one can read later. The
server part requires a web server with the PHP+SQLite support. The client side
relies only on HTML4+CSS2 making it accessible from any common web browser.

## Installation

1. Upload file `index.php` on the web server.

2. Access `index.php?install` from a web browser and click the _Install_ button.
   This step initializes the SQLite database.

3. Optional: Protect access to the page with HTTP basic authentication:

   Note: These steps are tailored for the Apache HTTP Server.

    1. Create a user and set the password:

            $ htpasswd -c .htpasswd <username>

       (The command will prompt for the password.)

    2. Upload the created file on the server. The file should be ideally saved
       in a location where it is available to the HTTP server but not actually
       served to the world. If this is not possible and the file needs to be
       stored in a public web directory then the server should be configured to
       not serve this file, for instance, by denying access to it in the main
       `http.conf` configuration file or using `.htaccess`. The provided
       `htaccess` template implements such a rule.

    3. Copy the `htaccess` template to `.htaccess` and change the `AuthUserFile`
       value to the absolute path of the `.htpasswd` file from the previous
       step.

    4. Upload file `.htaccess` on the server next to the `index.php` script.

## Logout

HTTP basic authentication does not provide facility to manage logging out and
this web application does not implement any of the possible workarounds for this
issue. Instead, logout can be achieved by the user by changing the URL to the
application to include a non-existent username, for instance,
`https://logout@book/`. This overrides the previous username which effectively
causes a logout.
