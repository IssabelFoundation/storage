%define modname storage

Summary: Issabel Storage
Name: issabel-storage
Version: 4.0.0
Release: 1
License: GPL
Group:   Applications/System
Source0: issabel-%{modname}-%{version}.tar.gz
BuildRoot: %{_tmppath}/%{name}-%{version}-root
BuildArch: noarch
Requires(pre): issabel-framework >= 2.3.0-5
Requires: issabel-system

# commands: cut
Requires: coreutils

%description
Issabel Storage

%prep
%setup -n %{name}-%{version}

%install
rm -rf $RPM_BUILD_ROOT

mkdir -p    $RPM_BUILD_ROOT%{_localstatedir}/www/html/
mkdir -p    $RPM_BUILD_ROOT%{_datadir}/issabel/privileged
mkdir -p    $RPM_BUILD_ROOT%{_datadir}/issabel/storage
mkdir -p    $RPM_BUILD_ROOT/usr/local/bin

mv modules/ $RPM_BUILD_ROOT%{_localstatedir}/www/html/
mv setup/usr/share/issabel/privileged/*  $RPM_BUILD_ROOT%{_datadir}/issabel/privileged
mv setup/usr/local/bin/storage.sh  $RPM_BUILD_ROOT/usr/local/bin
mv setup/usr/share/issabel/storage/.placeholder $RPM_BUILD_ROOT%{_datadir}/issabel/storage

rmdir setup/usr/share/issabel/privileged
rmdir setup/usr/share/issabel/storage
rmdir setup/usr/local/bin setup/usr/local

mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/systemd/system
mv setup/etc/systemd/system/storage.* $RPM_BUILD_ROOT%{_sysconfdir}/systemd/system

rmdir setup/etc/systemd/system
rmdir setup/etc/systemd
rmdir setup/etc

rmdir setup/usr/share/issabel setup/usr/share setup/usr

# The following folder should contain all the data that is required by the installer,
# that cannot be handled by RPM.
mkdir -p    $RPM_BUILD_ROOT%{_datadir}/issabel/module_installer/%{name}-%{version}-%{release}/
mv menu.xml $RPM_BUILD_ROOT%{_datadir}/issabel/module_installer/%{name}-%{version}-%{release}/

%pre

%post
pathModule="%{_datadir}/issabel/module_installer/%{name}-%{version}-%{release}"

# Run installer script to fix up ACLs and add module to Issabel menus.
issabel-menumerge $pathModule/menu.xml

systemctl enable storage.path
systemctl enable storage.service
systemctl start storage.path

%clean
rm -rf $RPM_BUILD_ROOT

%preun

if [ $1 -eq 0 ] ; then # Validation for desinstall this rpm
  echo "Delete Storage menus"
  issabel-menuremove "%{modname}"
  systemctl stop storage.path
  systemctl disable storage.path
  systemctl disable storage.service
fi

%files
%defattr(-, root, root)
%{_localstatedir}/www/html/modules/storage/*
%{_datadir}/issabel/module_installer/*
%defattr(644, root, root)
%{_sysconfdir}/systemd/system/storage.path
%{_sysconfdir}/systemd/system/storage.service
%{_datadir}/issabel/storage/.placeholder
%defattr(0755, root, root)
%{_datadir}/issabel/privileged/storage
/usr/local/bin/storage.sh

%changelog
