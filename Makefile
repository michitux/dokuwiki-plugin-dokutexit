#
# Makefile to publish a Dokuwiki plugin 
#
# http://danjer.doudouke.org/dokutexit
#
# Author : Danjer@doudouke.org
#


NAME = dokutexit
TAR_NAME = $(HOME)/www/$(NAME).tar.gz
ZIP_NAME = $(HOME)/www/$(NAME).zip

GNAME = graphviz
GSRC = $(HOME)/www/data/media/tech/dokutexit_graphviz_syntax.php.txt
GTAR_NAME = $(HOME)/www/$(GNAME).tar.gz
GZIP_NAME = $(HOME)/www/$(GNAME).zip


SRC =  syntax.php admin.php texitrender.php latex.php class.texitimage.php class.texitconfig.php Makefile

all : $(TAR_NAME) $(ZIP_NAME) $(GTAR_NAME) $(GZIP_NAME)

$(ZIP_NAME) : $(SRC)
	rm -rf /tmp/$(NAME)
	mkdir /tmp/$(NAME)
	cp -R * /tmp/$(NAME)
	find /tmp/$(NAME) -name '.svn' | xargs rm -rf
	find /tmp/$(NAME) -name '*~' | xargs rm -f
	find /tmp/$(NAME) -name 'semantic.cache' | xargs rm -f
	rm -f /tmp/$(NAME)/manager.dat
	rm -rf /tmp/$(NAME)/settings
	(cd /tmp; zip -r $(ZIP_NAME) $(NAME))
	rm -rf /tmp/$(NAME)

$(TAR_NAME) : $(SRC)
	rm -rf /tmp/$(NAME)
	mkdir /tmp/$(NAME)
	cp -R * /tmp/$(NAME)
	find /tmp/$(NAME) -name '.svn' | xargs rm -rf
	find /tmp/$(NAME) -name '*~' | xargs rm -f
	find /tmp/$(NAME) -name 'semantic.cache' | xargs rm -f
	rm -f /tmp/$(NAME)/manager.dat
	rm -rf /tmp/$(NAME)/settings
	(cd /tmp; tar zcvf $(TAR_NAME) $(NAME))
	rm -rf /tmp/$(NAME)


$(GZIP_NAME) : $(GSRC)
	rm -rf /tmp/$(GNAME)
	mkdir /tmp/$(GNAME)
	cp -R $(GSRC) /tmp/$(GNAME)/syntax.php
	find /tmp/$(GNAME) -name '.svn' | xargs rm -rf
	find /tmp/$(GNAME) -name '*~' | xargs rm -f
	find /tmp/$(GNAME) -name 'semantic.cache' | xargs rm -f
	rm -f /tmp/$(GNAME)/manager.dat
	rm -rf /tmp/$(GNAME)/settings
	(cd /tmp; zip -r $(GZIP_NAME) $(GNAME))
	rm -rf /tmp/$(GNAME)

$(GTAR_NAME) : $(GSRC)
	rm -rf /tmp/$(GNAME)
	mkdir /tmp/$(GNAME)
	cp -R $(GSRC) /tmp/$(GNAME)/syntax.php
	find /tmp/$(GNAME) -name '.svn' | xargs rm -rf
	find /tmp/$(GNAME) -name '*~' | xargs rm -f
	find /tmp/$(GNAME) -name 'semantic.cache' | xargs rm -f
	rm -f /tmp/$(GNAME)/manager.dat
	rm -rf /tmp/$(GNAME)/settings
	(cd /tmp; tar zcvf $(GTAR_NAME) $(GNAME))
	rm -rf /tmp/$(GNAME)


clean: 	
	find . -name '*~' | xargs rm -f	
	find . -name 'semantic.cache' | xargs rm -f

fclean: clean
	rm -f $(TAR_NAME)
	rm -f $(ZIP_NAME)
