<h1>Oauth keycloak login for HESK</h1>
Code works with release <b>HESK 3.4.6</b>.
<h2>How to use</h2>
Copy all directories to the main HESK installation, but if your admin folder in hesk settings has a different name, then before copying you need to change the admin directory to your name.
<br/><br/>

In the data.php file, which is located in the keycloak directory, you need to paste the data from the keycloak client. More information about Keycloak configuration can be found at https://www.keycloak.org/documentation.html
<br/><br/>

Of course, the login form has many social buttons, but if your organization uses only one of them, you should remove the rest. You should remember that the parameter in button <code>?kc_idp_hint=google</code> means the name of your identity provider, what configuration you have made in the keycloak realm.
<h2>Screenshot</h2>
Below you can see what the login form will look like. Of course, you can customize and use only one provider if you want.<br/><br/>

![login_page1](https://github.com/user-attachments/assets/960e9f16-9961-4e90-950e-2bed5f04ef09)