using System;
using System.Runtime.InteropServices;

class Program
{
    // Import FK623 DLL functions (names from manual)
    [DllImport("FK623Attend.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.Cdecl)]
    public static extern int FK_ConnectNet(int nMachineNo, string pstrIpAddress, int nNetPort, int nTimeOut, int nProtocolType, int nNetPassword, int nLicense);

    [DllImport("FK623Attend.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.Cdecl)]
    public static extern int FK_DisConnect(int nHandleIndex);

    [DllImport("FK623Attend.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.Cdecl)]
    public static extern int FK_LoadGeneralLogData(int nHandleIndex, int nReadMark);

    [DllImport("FK623Attend.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.Cdecl)]
    public static extern int FK_GetGeneralLogData_1(int nHandleIndex, out int apnEnrollNumber, out int apnVerifyMode, out int apnInOutMode, out int apnYear, out int apnMonth, out int apnDay, out int apnHour, out int apnMinute, out int apnSec);

    static int Main(string[] args)
    {
        if (args.Length < 3)
        {
            Console.WriteLine("Usage: FKBridge.exe <ip> <port> <license>");
            return 2;
        }

        string ip = args[0];
        if (!int.TryParse(args[1], out int port)) port = 5005;
        if (!int.TryParse(args[2], out int license)) license = 0;

        int machineNo = 1;
        int timeout = 5000;
        int protocol = 0; // TCP
        int netPassword = 0;

        try
        {
            int handle = FK_ConnectNet(machineNo, ip, port, timeout, protocol, netPassword, license);
            if (handle <= 0)
            {
                Console.WriteLine("CONNECT_FAIL:" + handle);
                return 3;
            }
            Console.WriteLine("CONNECTED:" + handle);

            int r = FK_LoadGeneralLogData(handle, 0);
            if (r != 1)
            {
                Console.WriteLine("LOAD_FAIL:" + r);
                FK_DisConnect(handle);
                return 4;
            }

            Console.WriteLine("RECORDS:");
            int rec = 0;
            while (true)
            {
                int enroll, vmode, iomode, year, month, day, hour, minute, sec;
                int res = FK_GetGeneralLogData_1(handle, out enroll, out vmode, out iomode, out year, out month, out day, out hour, out minute, out sec);
                if (res == 1)
                {
                    rec++;
                    Console.WriteLine($"{enroll},{vmode},{iomode},{year:D4}-{month:D2}-{day:D2} {hour:D2}:{minute:D2}:{sec:D2}");
                }
                else if (res == -7) // RUNERR_LOG_END
                {
                    Console.WriteLine("END");
                    break;
                }
                else
                {
                    Console.WriteLine("GET_ERR:" + res);
                    break;
                }
            }

            FK_DisConnect(handle);
            return 0;
        }
        catch (DllNotFoundException)
        {
            Console.WriteLine("DLL_NOT_FOUND");
            return 5;
        }
        catch (Exception ex)
        {
            Console.WriteLine("EXCEPTION:" + ex.Message);
            return 6;
        }
    }
}
