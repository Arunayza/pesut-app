Set args = WScript.Arguments
If args.Count < 2 Then WScript.Quit 1
inFile = args(0)
outFile = args(1)

On Error Resume Next
Set word = CreateObject("Word.Application")
word.Visible = False
word.DisplayAlerts = 0

Set doc = word.Documents.Open(inFile)
doc.SaveAs outFile, 17
doc.Close 0

word.Quit
Set doc = Nothing
Set word = Nothing
