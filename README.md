# Utility Styler WordPress Plugin

## An experimental plugin for adding the capability to write Tailwind classes in Gutenberg

This plugin is experimental and is not tested or designed for production use. It's a small plugin that enables writing Tailwind CSS as class names for any Gutenberg block that has custom class support. 

## How it works (if it works?)

UnoCSS runtime parser is integrated to create the CSS output on the fly. Initially this is stashed into a <style> tag. So when you're working in Gutenberg you should see the styles applied immediately, or with a slight delay, unless the specificity is too low. If you don't see the styles applied it's either being stomped by an existing block style (which is possible/likely for some styles) or you wrote an invalid Tailwind class name.

When you visit the page on the front-end for the first time, UnoCSS runs again, but this time an API request is sent to parse and store the Tailwind classes in a custom database table. The post is also flagged as parsed, until you edit it again. On subsequents visits to the page a generated CSS (normal CSS file with Tailwind classes) is enqueued instead of the UnoCSS. This is the vital aspect of being able to achieve 2 goals:

1. Avoid always using a runtime generator (which is not suitable for production despite being surprisingly fast).
2. Avoid requiring an NPM or CLI build of the Tailwind classes.

   ## Will other Utility class names work?

   Maybe? If you're familiar with UnoCSS you'll know it branches off from Tailwind and supports other utility class approaches. The runtime parser is setup for Tailwind, but it might also parse other naming approaches... that's something to test and play around with, but the intent so far has just been to use Tailwind.
