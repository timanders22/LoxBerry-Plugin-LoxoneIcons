#!/usr/bin/perl

# Copyright 2023 Michael Schlenstedt, michael@loxberry.de
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

##########################################################################
# Modules
##########################################################################

use CGI;
use LoxBerry::System;
use LoxBerry::JSON;
use LoxBerry::Log;
use warnings;
use strict;

##########################################################################
# Variables
##########################################################################

my $log;

# Read Form
my $cgi = CGI->new;
my $q = $cgi->Vars;

my $version = LoxBerry::System::pluginversion();
my $template;

# Language Phrases
my %L;

# Archives offered for download. The first one is created by
# download_icons.sh, the other two ship with the plugin.
my @archives = (
	{ file => "loxone_icons/loxone_icons.zip",   label => "ARCHIVE_LOXONE"  },
	{ file => "weather_icons/weather_icons.zip", label => "ARCHIVE_WEATHER" },
	{ file => "mixed_icons/mixed_icons.zip",     label => "ARCHIVE_MIXED"   },
);

##########################################################################
# AJAX
##########################################################################

if( $q->{ajax} ) {

	## Logging for ajax requests
	$log = LoxBerry::Log->new (
		name => 'AJAX',
		filename => "$lbplogdir/ajax.log",
		stderr => 1,
		loglevel => 7,
		addtime => 1,
		append => 1,
		nosession => 1,
	);

	LOGSTART "P$$ Ajax call: $q->{ajax}";

	## Handle all ajax requests
	my %response;
	ajax_header();

	# Start a new download
	if( $q->{ajax} eq "refresh" ) {
		$response{error} = &refresh();
		print JSON->new->canonical(1)->encode(\%response);
	}

	# How far along is it?
	if( $q->{ajax} eq "status" ) {
		my $status = &status();
		print JSON->new->canonical(1)->encode($status);
	}

	exit;

##########################################################################
# Normal request (not AJAX)
##########################################################################

} else {

	require LoxBerry::Web;

	# Init Template
	$template = HTML::Template->new(
	    filename => "$lbptemplatedir/settings.html",
	    global_vars => 1,
	    loop_context_vars => 1,
	    die_on_bad_params => 0,
	);
	%L = LoxBerry::System::readlanguage($template, "language.ini");

	# Default is the download form
	$q->{form} = "download" if !$q->{form};

	if ($q->{form} eq "download") { &form_download() }
	elsif ($q->{form} eq "log") { &form_log() }

	# Print the form
	&form_print();
}

exit;


##########################################################################
# Form: DOWNLOAD
##########################################################################

sub form_download
{
	$template->param("FORM_DOWNLOAD", 1);
	$template->param("ARCHIVES", &archivelist());
	$template->param("RUNNING", -e "$lbpdatadir/download.running" ? 1 : 0);
	$template->param("ICONCOUNT", &iconcount());
	return();
}


##########################################################################
# Form: Log
##########################################################################

sub form_log
{
	$template->param("FORM_LOG", 1);
	$template->param("LOGLIST", LoxBerry::Web::loglist_html());
	return();
}

##########################################################################
# Print Form
##########################################################################

sub form_print
{
	# Navbar
	our %navbar;

	$navbar{10}{Name} = "$L{'COMMON.LABEL_DOWNLOAD'}";
	$navbar{10}{URL} = 'index.cgi?form=download';
	$navbar{10}{active} = 1 if $q->{form} eq "download";

	$navbar{99}{Name} = "$L{'COMMON.LABEL_LOG'}";
	$navbar{99}{URL} = 'index.cgi?form=log';
	$navbar{99}{active} = 1 if $q->{form} eq "log";

	# Template
	LoxBerry::Web::lbheader($L{'COMMON.LABEL_PLUGINTITLE'} . " V$version", "https://wiki.loxberry.de/plugins/loxoneicons/start", "");
	print $template->output();
	LoxBerry::Web::lbfooter();

	exit;

}


##########################################################################
# Helpers
##########################################################################

# The archives with size and date, so the page shows whether an archive is
# actually there and how old it is.
sub archivelist
{
	my @list;
	foreach my $a (@archives) {
		my $path = "$lbpdatadir/$a->{file}";
		my %row = (
			NAME => $L{"DOWNLOAD.$a->{label}"},
			URL  => "./files/$a->{file}",
		);
		if ( -e $path ) {
			my @s = stat($path);
			my @t = localtime($s[9]);
			$row{EXISTS} = 1;
			$row{SIZE}   = sprintf("%.1f", $s[7] / 1048576);
			$row{DATE}   = sprintf("%04d-%02d-%02d %02d:%02d",
			                       $t[5]+1900, $t[4]+1, $t[3], $t[2], $t[1]);
		} else {
			$row{EXISTS} = 0;
		}
		push @list, \%row;
	}
	return \@list;
}

# How many icons were downloaded so far.
sub iconcount
{
	my @f = glob("$lbpdatadir/loxone_icons/svg/filled/*.svg");
	return scalar(@f);
}


######################################################################
# AJAX functions
######################################################################

sub ajax_header
{
	print $cgi->header(
			-type => 'application/json',
			-charset => 'utf-8',
			-status => '200 OK',
	);
}


# Downloading the whole set takes several minutes, far longer than a
# browser is willing to wait. The script is therefore started detached and
# the page asks for the state afterwards.
sub refresh
{
	my $errors;
	if ( -e "$lbpdatadir/download.running" ) {
		LOGWARN "A download is already running.";
		return (1);
	}
	my $force = $q->{force} ? " --force" : "";

	# Hier stand bis 2.0.0:
	#     system("... &");
	#     if ($?) { $errors++; }
	# Das ist wirkungslos. Durch das & endet die Shell sofort, und $? traegt
	# nur, ob sich die Shell starten liess - nie, ob der Download geklappt
	# hat. Die Pruefung meldete also immer Erfolg, auch wenn das Skript gar
	# nicht vorhanden war.
	#
	# Geprueft wird stattdessen, was sich hier ueberhaupt pruefen laesst:
	# ob das Skript da und ausfuehrbar ist. Wie der Lauf ausgeht, holt die
	# Oberflaeche ohnehin ueber status() und die Sperrdatei download.running
	# ab - das ist der richtige Weg fuer einen Vorgang, der Minuten dauert.
	my $skript = "$lbpbindir/download_icons.sh";
	if ( ! -x $skript ) {
		LOGERR "$skript fehlt oder ist nicht ausfuehrbar.";
		return (1);
	}
	system("$skript$force >/dev/null 2>&1 &");
	if ( $? == -1 ) {
		LOGERR "Der Download liess sich nicht anstossen: $!";
		$errors++;
	}
	return ($errors);
}


sub status
{
	my %st;
	$st{running} = -e "$lbpdatadir/download.running" ? 1 : 0;
	$st{icons}   = &iconcount();
	$st{zip}     = -s "$lbpdatadir/loxone_icons/loxone_icons.zip" ? 1 : 0;
	return (\%st);
}


END {
	if($log) {
		LOGEND;
	}
}
