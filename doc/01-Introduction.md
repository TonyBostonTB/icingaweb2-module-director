Introduction
============

Welcome to the Icinga Director, the bleeding edge configuration tool for
Icinga 2! Developed as an Icinga Web 2 module it aims to be your new
favorite Icinga config deployment tool. Even if you prefer plain text
files and manual configuration, chances are good that the Director will
change your mind.

Director is here to make your life easier. As a Icinga 2 pro you know
all the knobs an tricks Icinga2 provides. However, you are not willing
to do the same work again and again. Someone wants to add a new server,
tweak some thresholds, adjust notifications? They shouldn't need to
bother you.

No way, you might think. You do not trust your users, they might break
things. Well... no. Not with the Director. It provides an audit log that
shows any single change. You can re-deploy old configurations at any time.
And you will be allowed to restrict what your users are allowed to do in
a very granular way.

Doing automation? Want to feed your monitoring from your configuration
management tool, or from your CMDB? You'll love the endless possibilities
Director provides.


Basic architecture
------------------

Icinga Director uses the Icinga 2 API to talk to your monitoring system.
It will help you to deploy your configuration, regardless of whether you
are using a single node Icinga installation or a distributed setup with
multiple masters and satellites.

    +------------+     +--------------+    +------------+
    | Sat 1 / EU |     | Sat 2 / Asia |    | Sat 3 / US |
    +------------+     +--------------+    +------------+
            \           /                    /
             \         /                    /
           +-------------+       +-------------+
           |  Master 1   | <===> |  Master 2   |  (Master-Zone)
           +-------------+       +-------------+
                 ^                       ^
                 |   Icinga 2 REST API   :
                 |                       :
               +----------------------------+
               |       Icinga Director      |
               +----------------------------+

Using the Icinga 2 Agent? Perfect, the Director will make your life much
easier!


Limited in your choices?
------------------------

Icinga2 configuration is a full-blown DSL, not just a configuration format.
It provides endless possibilities, the very same thing can be accomplished
in various ways. Director tries hard to offer you as many of those while
strictly trying to keep things simple.

You are absoletely right if you might think that this is not an easy task.
We do our best to offer you as much flexibility as possible. In case you
miss a feature or have a cool idea of what else we could add please let
us know. Just file an issue or a feature request at our issue tracker:

  https://dev.icinga.org

The Icinga project is and remains Open Source Software and lives from
community ideas and contributions.

