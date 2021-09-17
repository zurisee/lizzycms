
{{ vgap }}

---


## Please Note

You should create an admin account ASAP.

-> How to do that depends on how you are set up:

On a Localhost:
: If you are working on a local host, click on -> {{ link('?admin=first-user', 'add an admin-user', class:'lzy-button mybutton' ) }} and fill in your **e-mail address** and a **password**. {{ vgap( 1em ) }}

On a Remote Host:
: For security reasons that's not allowed on a remote host.  
: Instead, you have to do it manually:  
: 1. Copy the file ``_lizzy/_parent-folder/config/_samples/users.yaml`` to ``config/users.yaml``.  
: 2. Then define the admin user, i.e. uncomment the sample and enter your password and email.

{{ vgap( 1em ) }}
Note: passwords have to be entered in hashed form. To create a password hash, use the {{ link('?convert', 'password hash' ) }} tool.

