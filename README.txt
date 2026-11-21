=== PD Seat Planner ===
Contributors: sidouglas
Donate link: https://pacificdragons.com.au/
Tags: seating, dragon-boat, outrigger, oc6, events-manager
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lets session leads arrange the seating of paddlers in Dragon Boats and OC6 canoes, on the Events Manager event.

== Description ==

This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

A few notes about the sections above:

*   "Contributors" is a comma separated list of wp.org/wp-plugins.org usernames
*   "Tags" is a comma separated list of tags that apply to the plugin
*   "Requires at least" is the lowest version that the plugin will work on
*   "Tested up to" is the highest version that you've *successfully used to test the plugin*. Note that it might work on
higher versions... this is just the highest one you've verified.
*   Stable tag should indicate the Subversion "tag" of the latest stable version, or "trunk," if you use `/trunk/` for
stable.

    Note that the `readme.txt` of the stable tag is the one that is considered the defining one for the plugin, so
if the `/trunk/readme.txt` file says that the stable tag is `4.3`, then it is `/tags/4.3/readme.txt` that'll be used
for displaying information about the plugin.  In this situation, the only thing considered from the trunk `readme.txt`
is the stable tag pointer.  Thus, if you develop in trunk, you can update the trunk `readme.txt` to reflect changes in
your in-development version, without having that information incorrectly disclosed about the current stable version
that lacks those changes -- as long as the trunk's `readme.txt` points to the correct stable tag.

    If no stable tag is provided, it is assumed that trunk is stable, but you should specify "trunk" if that's where
you put the stable version, in order to eliminate any doubt.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `pds-db-seating-planner.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 1.8.0 =
* Renamed to "Seat Planner" — now supports OC6 outrigger canoes alongside Dragon Boats.
* Layout is chosen from the event category (dragon-boat / oc6); a populated plan keeps its own layout if the event is re-categorised. Events tagged both show the Dragon Boat layout with a notice.
* OC6 boards are a single file of 6 seats (seat 6 steers), no weights overlay or Boat Load (weight is irrelevant for OC6).
* Default boat count scales to the crew: one boat per capacity, rounded up (OC6 6, Dragon Boat 21).
* Boats can be named (free text per boat, shown with 2+ boats) with a number badge; the Empty control is now a trashcan icon.
* Public event-page render for both boat types, with per-boat name/number headings; OC6 renders as neutral-grey seat boxes (no port/starboard colour). Draft plans stay hidden from non-coaches.

= 1.7.3 =
* Fixed mobile touch bugs: swap no longer dumps the dragged paddler into the available list; tapping the remove (x) no longer removes twice. Added a Playwright touch-interaction suite.

= 1.7.0 - 1.7.2 =
* Multi-ticket bookings seat as one seat per space; guest seats inherit the member's weight; removed the coaching-notes textarea (superseded by the coaching-plans CPT).

== Upgrade Notice ==

= 1.0 =
Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`