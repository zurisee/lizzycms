# lizzycms
Flat-file CMS inspired by lizzards - elegant, nifty and agile

---

## What is Lizzy?

A Flat-File Content Management System for small and medium sized Web Presences.

In a nutshell:
you create your web presence on a local computer, upload it to a web host and bang! it works.

You modify something on the host – and it still works.

That means, Lizzy is fully self-contained, its entire production tool-chain is on board. Yet, it's still small and handy. And features a couple of pretty nifty options that let you implement even advanced design patterns in a breeze.

## Installation

- Start from a folder under your web-server's doc-root (let's call it 'project folder')
- Clone Lizzy into a subfolder of your project folder, name the subfolder '_lizzy/'
- Copy `_lizzy/_parent-folder/index.php` to your project folder (i.e. two up)
- Open the project folder in your browser -> installation will be completed automatically
- Reload and you are ready to role

**Note**: this creates a skeleton website - no need to strip it first.

To add content, go to `pages/`, locate the desired folder and edit the `*.md` file.

To add pages, just add lines to `config/sitemap.txt`. 
Each line corresponds to a page. Indent to add a hierarchy. Most often there is no need for arguments to pages (which would be added in curly braces, like the example 'Home' shows).

## Documentation

Visit [getlizzy.net](https://getlizzy.net/)
