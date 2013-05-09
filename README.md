# Folder Field Type

A smooth way to embed the PyroCMS file manager interface in your admin forms. Pages, Streams, and other streams enabled modules can make use of it.

### Author

* [Jerel Unruh](http://jerel.co/)

### Legal

* Apache2 License
* &copy; Copyright 2013 Jerel Unruh

## Usage

1. Add it to addons/shared_addons/field_types with this field type's folder named `folder`.
2. Make sure at least one folder exists in CP > Content > Files for the uploads or subfolders to reside in.
3. Create a new field (in Page Types or Streams) using Folder as the type.
4. Now when you edit or create a Page or Stream Entry a simplifed file manager interface will be shown in the form.
5. Upload, edit, and delete files as you wish.

To output the files use the field slug in a double tag. For example if you named your field `Gallery` you would output the images like so:

    {{ gallery }}
        <img src="{{ url:site }}files/thumb/{{ id }}" alt="{{ name }}"/>
    {{ /gallery }}