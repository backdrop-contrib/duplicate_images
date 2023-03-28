Duplicate Images
================

The **Duplicate Images** module allows you to find and remove duplicate images
and other documents on the public or private file system.

Problem
-------
If editors want to add images or documents to their content, they can do so by
uploading these from their local computer. If the core Backdrop image library
search has not been used when an image is needed more than once, or in the case
of a site that has been upgraded from Drupal 7, it is possible to end up with
multiple copies of the same image. This may lead to a messy image library
interface, bloated file listings, longer backup times, larger backup sizes,
unreliable usage stats, unnecessary image derivative creation while an equal
image derivative could have been served directly.

Solution
--------
- To prevent duplicate images, use the core image library to search and reuse
  images, or use the module FileField Sources (1) which allows
  you to reuse already uploaded files, either managed or unmanaged.
- But if your site already has a (large) number of duplicates and you want to
  get rid of those, use this module. It will: find duplicates, find its usages,
  correct these usages and then delete the duplicates.

Differences from Drupal 7
-------------------------

- As part of the porting and testing process, I've implemented the use of
  session variables in this module. Theoretically this will allow using the back
  button if needed, which was not possible in the Drupal 7 version.

Documentation
-------------

Additional documentation is located in [the Wiki](https://github.com/backdrop-contrib/duplicate_images/wiki/Documentation).

Issues
------

Bugs and feature requests should be reported in [the Issue Queue](https://github.com/backdrop-contrib/duplicate_images/issues).

Current Maintainers
-------------------

- [Laryn Kragt Bakker](https://github.com/laryn).
- Seeking additional maintainers.

Credits
-------

- Ported to Backdrop CMS by [Laryn Kragt Bakker](https://github.com/laryn).
- Port sponsored by [Aten Design Group](https://atendesigngroup.com).
- Originally written for Drupal by [Erwin Derksen](https://www.drupal.org/u/fietserwin)
   of [Buro RaDer](https://burorader.com).

License
-------

This project is GPL v2 software.
See the LICENSE.txt file in this directory for complete text.
