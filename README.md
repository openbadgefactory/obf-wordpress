About the Open Badge Factory -plugin
============
Easily use badges and set up the steps and achievements users complete to earn them. Badges are Mozilla OBI compatible.


Description
------------

[Open Badge Factory](http://openbadgefactory.com "Open Badge Factory")&trade; is an Open Badge management system. This plugin turns your WordPress site into an badge awarding system. Your site's users complete steps, demonstrate skills and knowledge, and earn digital badges. Easily define the achievements, organize the badge requirements any way you like, and choose from and combine a range of assessment options to determine whether each task or requirement has been achieved.

Badges are Mozilla OBI compatible and sharable via [Open Badge Passport](http://openbadgepassport.com/ "openbadgepassport.com").

The Open Badge Factory -plugin is extremely powerful and infinitely extensible. Check out some of the out-of-the-box features:


**Many ways to define how to earn and give badges**

*   Reviewed submissions
*   Auto-approving submissions
*   Nominations and review
*   Site activity (triggers based on commenting and logging in to your site)
*   Completing specific other achievements one or a specific number of times
*   Completing one, select or all achievements of a specific type
*   Point thresholds
*   Admin Given Badges

**Define an Unlimited Number of Achievement Types**

*   Create as many types of achievement as you like
*   Name achievement types whatever you wish
*   Easily define how they relate to one another using the 'Required Steps' tool
*   Set default images for each achievement type or select unique images for every achievement item.

**Sharable Badges with Open Badge Factory Integration**

*   Badges are Mozilla Open Badge (OBI) compatible through integration of the Open Badge Factory API, the web service for creating, issuing and sharing badges.
*   Connect your Open Badge Factory account to your Open Badge Factory -plugin enabled Wordpress site and voila! You're using WordPress to issue "Open Badges" that can be displayed virtually anywhere.
*   Badges you create in Open Badge Factory automatically appear and update on your Open Badge Factory -plugin enabled Wordpress site.
*   As badges are earned on WordPress, they can be automatically sent to users via Open Badge Factory for easy sharing on Open Badge Passport, Facebook, Twitter, Mozilla Backpack or the users own blog or website.


**'Required Steps' Manager**

*   Simple yet powerful admin interface for defining the "Required Steps" for any badge or achievement.
*   Easily link together one or more triggers, steps or actions into the conditions needed to earn a badge or mark an achievement.


**Reward User Progress**

*   Issue badges for any combination of achievements
*   Award points for commenting, logging in, making submissions, completing tasks
*   Display a congratulatory message, customizable per Achievement, on each achievement page.


**Earned Achievements Widget**

* Shows logged in users what badges they have earned.
* Option to choose which specific achievement types to display in the widget.
* Set the parameters for the widget to decide how many recent badges to display.
* Option to show logged in users total points they have earned (if you are using Open Badge Factory points mechanism).


**Theme Agnostic & Shortcodes**

* Open Badge Factory works with just about any standard WordPress theme.
* No special hooks or theme updates are needed.
* Turn any page or post into a way to display available achievements and for users to track their progress.
* Multiple options and parameters for each for great flexibility.
* Shortcodes to bring submission and nomination review to the front-end of your site.
* Shortcode to integrate specific available achievements into any post or page of your site.
* Just activate the Open Badge Factory -plugin and place simple shortcodes on any page or post, and you've got an engagement management system running on your WordPress site!


**Submission and Nomination Review**

* Easily review submissions and nominations from members.
* Approve or deny submissions with one click
* Add comments to engage the member and elaborate on your decisions.
* Optional notification emails inform you when people on your site have made submissions or nominated peers.
* Shortcodes for easily creating front-end pages for displaying submissions and nominations.


Extensibility and Open Badge Factory Add-ons
------------
* The Open Badge Factory -plugin is designed to be a true operating system for turning any WordPress site into an engagement management application.
* The Open Badge Factory -plugin supports add-ons to the plugin that enhance core functionality with specialized functions.
* Built with expandability in mind to allow virtually anything to trigger and recognize achievement.

Stay Connected / Helpful Links
------------
If you are interested in developing the Open Badge Factory -plugin, you may be interested to check out these links.

* [Open Badge Factory](http://openbadgefactory.com/ "Open Badge Factory web site") - Contact Us, Video Tutorials, Signing Up, API Documentation, News
* [Open Badge Factory Developer Documentation](https://openbadgefactory.com/developers/ "Open Badge Factory Developer Docs and APIs") - Open documentation, APIs and resources for Open Badge Factory developers.
* [Twitter](https://twitter.com/OBFactory_ "Open Badge Factory on Twitter") - Open Badge Factory Tweets
* [Twitter](https://twitter.com/OBPassport "Open Badge Passport on Twitter") - Open Badge Passport Tweets
* [GitHub](https://github.com/discendum "Our repositories on GitHub") - Report issues, contribute code


License Info
------------

The Open Badge Factory wordpress plugin is licensed to you under the terms of the GNU Affero General Public License, version 3, as published by the Free Software Foundation.

There is NO WARRANTY for this software, express or implied, including the implied warranties of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License, version 3, at [http://www.gnu.org/licenses/agpl-3.0.html](http://www.gnu.org/licenses/agpl-3.0.html "License") for more details.


Installation
------------

A more detailed install guide can be cound [here](doc/install/index.md)

1. Upload 'obf' to the '/wp-content/plugins/' directory of your WordPress installation
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Enter Open Badge Factory API certficate for the badge Issuer in the OBF Integration settings to enable badge sharing. (First get an account at openbadgefactory.com if you do not have one.)
4. Set up awarding rules for your badges.
5. Visit the Help/Support section of the Open Badge Factory menu for shortcodes to turn any page or post on your site into a Open Badge Factory list of available achievements and badges.

Bugs & Feature Requests
------------
Please open an [Issue](https://github.com/discendum) to report any bugs or feature requests.


Contributing
------------
The Open Badge Factory -plugin team welcomes donations in the form of code contributions, add-ons, and shared love for what we’re doing. So spread the word and share back your innovations with the Open Badge Factory community.

Developer Documentation:  Check out a complete set of Developer Documentation, APIs and guides at [openbadgefactory.com/developers/](https://openbadgefactory.com/developers/). And share your Open Badge Factory Add-Ons and Open Badge Factory -compatible plugins.

Want to contribute to the Open Badge Factory -plugin core? That's great! Patches are always welcome. Open an Issue and make a Pull Request.

1. Open an Issue (or claim an open Issue).
2. [Fork the Open Badge Factory -plugin.](https://github.com/discendum)
3. Create a new branch.
4. Commit your changes (`git commit -am "Added the best feature ever!"`).
5. Push to the branch back to GitHub (`git push origin MyFeature`).
6. Open a Pull Request.
7. Select our master branch as the base branch for your contribution.
8. Describe and submit your pull request.
9. Enjoy a refreshing beverage and wait.

Developer Info
--------------

This project uses Composer to manage dependencies. If you don't have Composer installed, run the following command to install it:

    curl -sS https://getcomposer.org/installer | php

And then, install the project dependencies using Composer:

    php composer.phar install --no-dev

