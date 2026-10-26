Option Explicit

' Run Laravel's scheduler without creating a visible console window.
Dim shell
Set shell = CreateObject("WScript.Shell")
shell.CurrentDirectory = "C:\Users\chari\e-doc"
shell.Run """C:\xampp\php\php.exe"" ""C:\Users\chari\e-doc\artisan"" schedule:run", 0, True
