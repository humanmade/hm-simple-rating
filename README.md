# Simple Rating

This adds a widget that can be added to post types or taxonomy terms and will 
allow visitors to rate that item with a "thumbs up" or "thumbs down".

## Usage

To use this plugin, activate the plugin and then add the "Rate Item" widget to
any widget areas where you want it to appear.

Note that it operates off of the ID of the item on which it is placed, which
it determines from global values. For more advanced implementations, you can
call it directly in your templates and pass an ID, type, etc.

## Cookies and Personally Identifying Information

This plugin will create a cookie for each visitor. The purpose of this cookie is
to prevent (or at least discourage) repeat voting, and to provide a vistor with
confirming feedback (i.e. "these buttons are disabled because you have voted").
Without the cookie, the rating mechanism still functions, but a visitor can vote
as many times as they like by continuing to click the rating buttons.

The cookie stores the following information, which shouldn't constitute
personally identifying information:

- The ID of the post or term where the vote was made
- The vote itself, stored as a `1` (yes) or `0` (no)
- The URI path of the resource

The cookie is instructed to persist for a year.

**Currently, the plugin does not notify visitors that it is creating a cookie.** 
For internal usage, this should be fine, but if sites using this feature are 
later made public, we would need to re-evaluate compliance with privacy 
legislation, i.e. GDPR, CCPA, etc.
