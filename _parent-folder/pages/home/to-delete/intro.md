
{{ vgap }}

---

## Please Note

You should create an admin account ASAP.  

{{ if( state:'isLocalhost', 
	then:'%contentFrom:#localhost',
	else:'%contentFrom:#remotehost',
	) 
}}

::: #localhost	!off
To do so, click on -> {{ link('?admin=add-user&lzy-preset-groups=admins', 'add an admin-user', class:'lzy-button mybutton' ) }} and fill in your **e-mail address** and a **password**.

To do it manually, 
you need to create the file ``config/users.yaml`` (or rename config/#users.yaml) and 
define an admin user.

::: #remotehost	!off
To do so 
you need to create the file ``config/users.yaml`` (or rename config/#users.yaml) and 
define an admin user.
:::
